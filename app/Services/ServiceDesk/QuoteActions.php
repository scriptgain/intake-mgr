<?php

namespace App\Services\ServiceDesk;

use App\Models\Quote;
use App\Models\Setting;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * The behaviour behind a quote's lifecycle, shared by the admin panel, the
 * customer portal, and the public token link so accept/decline/convert do the
 * same thing (and record the same timeline) wherever they are triggered.
 */
class QuoteActions
{
    /**
     * Accept a quote. Idempotent: a quote already past `sent` is left alone.
     * When `quotes_auto_invoice_on_accept` is on (default) and no invoice exists
     * yet, the invoice is generated immediately so it appears in the customer's
     * Billing to pay — the admin Convert action still governs the work order.
     *
     * @param  string|null  $actorName  Set for a customer action (staff actions
     *                                  fall back to the authenticated user id).
     */
    public static function accept(Quote $quote, ?string $actorName = null): void
    {
        if (! in_array($quote->status, ['draft', 'sent'], true)) {
            return;
        }

        DB::transaction(function () use ($quote, $actorName) {
            $quote->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();
            $quote->recordActivity('status', 'Quote Accepted', [], null, $actorName);

            if (Setting::get('quotes_auto_invoice_on_accept', '1') === '1' && ! $quote->invoice_order_id) {
                $invoice = InvoiceBuilder::generate(
                    $quote,
                    array_filter(['quote_id' => $quote->id, 'project_id' => $quote->project_id]),
                    'Quote '.$quote->number
                );

                $quote->forceFill(['invoice_order_id' => $invoice->id])->save();
                $quote->recordActivity('payment', 'Invoice '.$invoice->number.' Generated', ['order_id' => $invoice->id], null, $actorName);
            }
        });
    }

    public static function decline(Quote $quote, ?string $reason = null, ?string $actorName = null): void
    {
        if (! in_array($quote->status, ['draft', 'sent'], true)) {
            return;
        }

        $quote->forceFill([
            'status' => 'declined',
            'declined_at' => now(),
            'decline_reason' => $reason,
        ])->save();

        $quote->recordActivity('status', 'Quote Declined'.($reason ? ': '.$reason : ''), [], null, $actorName);
    }

    /**
     * Convert an accepted quote into the chosen targets. Each target is created
     * only if it does not already exist, so a second convert is a no-op.
     *
     * @param  array<int,string>  $targets  Any of 'invoice', 'work_order'.
     * @return array<string,mixed>  The created records, keyed by target.
     */
    public static function convert(Quote $quote, array $targets): array
    {
        $created = [];

        DB::transaction(function () use ($quote, $targets, &$created) {
            if (in_array('invoice', $targets, true) && ! $quote->invoice_order_id) {
                $invoice = InvoiceBuilder::generate(
                    $quote,
                    array_filter(['quote_id' => $quote->id, 'project_id' => $quote->project_id]),
                    'Quote '.$quote->number
                );

                $quote->forceFill(['invoice_order_id' => $invoice->id])->save();
                $quote->recordActivity('payment', 'Invoice '.$invoice->number.' Generated', ['order_id' => $invoice->id]);
                $created['invoice'] = $invoice;
            }

            if (in_array('work_order', $targets, true) && ! $quote->work_order_id) {
                $workOrder = WorkOrder::create([
                    'customer_id' => $quote->customer_id,
                    'project_id' => $quote->project_id,
                    'title' => $quote->title,
                    'notes' => $quote->message,
                    'status' => 'scheduled',
                    'address' => $quote->address,
                    'currency' => $quote->currency,
                ]);

                foreach ($quote->items as $item) {
                    $workOrder->items()->create([
                        'service_id' => $item->service_id,
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'unit_price_cents' => $item->unit_price_cents,
                        'total_cents' => $item->total_cents,
                    ]);
                }

                $workOrder->recalcTotals();
                $workOrder->recordActivity('created', 'Created From Quote '.$quote->number);

                $quote->forceFill(['work_order_id' => $workOrder->id])->save();
                $quote->recordActivity('converted', 'Converted To Work Order '.$workOrder->number, ['work_order_id' => $workOrder->id]);
                $created['work_order'] = $workOrder;
            }

            $quote->forceFill(['status' => 'converted', 'converted_at' => now()])->save();
        });

        return $created;
    }
}
