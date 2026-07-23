<?php

namespace App\Services\ServiceDesk;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;

/**
 * Builds an invoice (an Order) from a line-item source — a WorkOrder or a Quote.
 * Both freeze the service name + price on their items, so the invoice is a
 * faithful, immutable copy. Money is never re-derived here beyond falling back
 * to the subtotal when the source carries no discount/tax columns.
 */
class InvoiceBuilder
{
    /**
     * @param  object  $source  A model exposing ->customer, ->customer_id,
     *                          ->currency, ->subtotal_cents (and optionally
     *                          ->discount_cents / ->tax_cents / ->total_cents),
     *                          and ->items (each with name, quantity,
     *                          unit_price_cents, total_cents).
     * @param  array<string,mixed>  $links  Foreign keys to set on the order,
     *                                       e.g. ['work_order_id' => 5] or
     *                                       ['quote_id' => 9, 'project_id' => 2].
     * @param  string  $sourceLabel  Human label for the timeline event.
     */
    public static function generate(object $source, array $links, string $sourceLabel): Order
    {
        $customer = $source->customer;

        $subtotal = (int) $source->subtotal_cents;
        $discount = (int) ($source->discount_cents ?? 0);
        $tax = (int) ($source->tax_cents ?? 0);
        $total = (int) ($source->total_cents ?? ($subtotal - $discount + $tax));

        $invoice = Order::create(array_merge([
            'number' => Order::nextNumber(),
            'customer_id' => $source->customer_id,
            'email' => $customer?->email ?? '',
            'phone' => $customer?->phone,
            'status' => 'open',
            'financial_status' => 'pending',
            'fulfillment_status' => 'fulfilled',
            'currency' => $source->currency ?? config('shop.currency', 'USD'),
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'shipping_cents' => 0,
            'tax_cents' => $tax,
            'total_cents' => $total,
            'payment_gateway' => Setting::get('default_gateway', 'stripe'),
        ], $links));

        foreach ($source->items as $item) {
            OrderItem::create([
                'order_id' => $invoice->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'total_cents' => $item->total_cents,
                'requires_shipping' => false,
            ]);
        }

        $invoice->recordEvent('placed', 'Invoice Generated From '.$sourceLabel);

        return $invoice;
    }
}
