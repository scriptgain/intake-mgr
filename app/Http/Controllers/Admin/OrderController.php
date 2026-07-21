<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\OrderMailer;
use App\Services\Payments\OrderPayments;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private CheckoutService $checkout) {}

    public function index(Request $request)
    {
        $orders = Order::with('customer')
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('financial'), fn ($q) => $q->where('financial_status', $request->string('financial')))
            ->when($request->filled('fulfillment'), fn ($q) => $q->where('fulfillment_status', $request->string('fulfillment')))
            ->latest()
            ->paginate((int) config('shop.rows_per_page', 25))
            ->withQueryString();

        $filters = $request->only(['q', 'status', 'financial', 'fulfillment']);

        $tabCounts = [
            'all' => Order::count(),
            'unfulfilled' => Order::where('status', 'open')->where('fulfillment_status', 'unfulfilled')->count(),
            'unpaid' => Order::where('status', 'open')->where('financial_status', 'pending')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'tabCounts' => $tabCounts,
            // Built here rather than in the template: working out which tab is
            // active means comparing filter sets, which is logic.
            'tabs' => $this->indexTabs($filters, $tabCounts),
        ]);
    }

    /**
     * Status tabs for the orders index, each already resolved to its URL and
     * active state.
     */
    private function indexTabs(array $filters, array $counts): array
    {
        $definitions = [
            ['key' => 'all', 'label' => 'All', 'params' => []],
            ['key' => 'unfulfilled', 'label' => 'Unfulfilled', 'params' => ['status' => 'open', 'fulfillment' => 'unfulfilled']],
            ['key' => 'unpaid', 'label' => 'Unpaid', 'params' => ['status' => 'open', 'financial' => 'pending']],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'params' => ['status' => 'cancelled']],
        ];

        $current = array_filter([
            'status' => $filters['status'] ?? null,
            'financial' => $filters['financial'] ?? null,
            'fulfillment' => $filters['fulfillment'] ?? null,
        ]);

        $activeKey = 'all';
        foreach ($definitions as $tab) {
            if ($tab['params'] == $current) {
                $activeKey = $tab['key'];
                break;
            }
        }

        $search = array_filter(['q' => $filters['q'] ?? null]);

        return array_map(fn ($tab) => [
            'label' => $tab['label'],
            'count' => $counts[$tab['key']] ?? 0,
            'active' => $tab['key'] === $activeKey,
            'href' => route('orders.index', array_merge($tab['params'], $search)),
        ], $definitions);
    }

    public function show(Order $order)
    {
        $order->load(['items.variant', 'events.user', 'fulfillments', 'customer']);

        return view('admin.orders.show', [
            'order' => $order,
            // Only lines with something left to ship appear on the fulfill form.
            'fulfillableItems' => $order->items->filter(
                fn ($item) => $item->requires_shipping && $item->unfulfilled_qty > 0
            ),
            'carriers' => ['UPS', 'USPS', 'FedEx', 'DHL', 'Other'],
            // Addresses arrive as raw JSON columns. Flattening them into
            // display lines is formatting logic, so it happens here and the
            // template just prints the lines.
            'shippingLines' => $this->addressLines($order->shipping_address),
            'billingLines' => $this->addressLines($order->billing_address),
        ]);
    }

    /** Flatten a stored address into the lines a human would write on a label. */
    private function addressLines(?array $address): array
    {
        if (! $address) {
            return [];
        }

        $city = trim(
            ($address['city'] ?? '')
            .(! empty($address['state']) ? ', '.$address['state'] : '')
            .' '.($address['postcode'] ?? '')
        );

        return array_values(array_filter([
            trim(($address['first_name'] ?? '').' '.($address['last_name'] ?? '')),
            $address['company'] ?? null,
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            $city,
            $address['country'] ?? null,
            $address['phone'] ?? null,
        ]));
    }

    /** Record a shipment against some or all of the order's line items. */
    public function fulfill(Request $request, Order $order)
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
            'carrier' => ['nullable', 'string', 'max:64'],
            'tracking_number' => ['nullable', 'string', 'max:128'],
            'tracking_url' => ['nullable', 'url', 'max:255'],
        ]);

        DB::transaction(function () use ($order, $data, $request) {
            $shipped = [];

            foreach ($data['quantities'] as $itemId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }

                $item = $order->items()->find($itemId);
                if (! $item) {
                    continue;
                }

                // Never let a typo ship more units than were ordered.
                $qty = min($qty, $item->unfulfilled_qty);
                if ($qty <= 0) {
                    continue;
                }

                $item->increment('fulfilled_qty', $qty);
                $shipped[] = ['order_item_id' => $item->id, 'quantity' => $qty];
            }

            if (! $shipped) {
                return;
            }

            $order->fulfillments()->create([
                'user_id' => auth()->id(),
                'status' => 'shipped',
                'carrier' => $data['carrier'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'tracking_url' => $data['tracking_url'] ?? null,
                'items' => $shipped,
                'notify_customer' => $request->boolean('notify_customer'),
                'shipped_at' => now(),
            ]);

            $order->syncFulfillmentStatus();
            $order->recordEvent(
                'fulfilled',
                'Shipment Created'.($data['tracking_number'] ?? false ? ': '.$data['tracking_number'] : ''),
                ['items' => $shipped, 'carrier' => $data['carrier'] ?? null]
            );
        });

        return back()->with('status', 'Fulfillment recorded.');
    }

    /** Mark a manual/offline payment as received. */
    public function markPaid(Request $request, Order $order)
    {
        $request->validate(['reference' => ['nullable', 'string', 'max:128']]);

        $this->checkout->markPaid($order, $request->string('reference')->toString() ?: null);

        return back()->with('status', 'Order marked as paid.');
    }

    /**
     * Refund an order, full or partial.
     *
     * A card order is refunded THROUGH STRIPE: the money actually goes back. A
     * manual/offline order is recorded only, because the merchant moved that
     * money themselves and we have no rail to reverse it on.
     *
     * The amount is re-bounded inside OrderPayments::refund against the order's
     * own refundable balance, so a tampered form field cannot refund more than
     * was charged even though it is validated here too.
     */
    public function refund(Request $request, Order $order)
    {
        $data = $request->validate([
            // 'full' is a distinct mode rather than the merchant retyping the
            // total, so a full refund can never be a cent short of full.
            'mode' => ['required', Rule::in(['full', 'partial'])],
            'amount' => ['required_if:mode,partial', 'nullable', 'string'],
            'reason' => ['nullable', Rule::in(['duplicate', 'fraudulent', 'requested_by_customer', ''])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $remaining = $order->refundable_cents;

        // withErrors AND a session warning: the admin layout renders session
        // alerts but not the error bag, and the error bag alone would only
        // surface inside the refund modal, which is closed. A refund that
        // failed must never look like nothing happened.
        if ($remaining < 1) {
            return $this->refundFailed('There is nothing left to refund on this order.');
        }

        if ($data['mode'] === 'full') {
            $amount = null; // Let the service use the full refundable balance.
        } else {
            $amount = Money::parse($data['amount']) ?? 0;

            if ($amount <= 0 || $amount > $remaining) {
                return $this->refundFailed(
                    'Enter an amount between '.Money::format(1).' and '.Money::format($remaining).'.'
                );
            }
        }

        $result = OrderPayments::refund(
            $order,
            $amount,
            // validate() only returns keys that were actually present, so an
            // omitted reason is a missing key, not an empty string.
            ($data['reason'] ?? null) ?: null,
            $request->user()
        );

        if (! $result['ok']) {
            return $this->refundFailed($result['error']);
        }

        // Money leaving the business is an audited action, always.
        AuditLog::record(
            'refunded',
            'Refunded '.Money::format($result['amount_cents']).' on order '.$order->number
                .($order->has_card_payment ? ' via Stripe' : ' (recorded only)'),
            $order->fresh()
        );

        return back()->with('status', 'Refunded '.Money::format($result['amount_cents']).'.');
    }

    /** Surface a refund failure both ways, so it is visible on the page. */
    private function refundFailed(string $message)
    {
        return back()
            ->with('warning', $message)
            ->withErrors(['amount' => $message]);
    }

    /** Re-send the customer's confirmation email on request. */
    public function resendEmail(Order $order)
    {
        $sent = OrderMailer::resend($order);

        if (! $sent) {
            return back()
                ->with('warning', 'Could not send that email. Check the SMTP settings.')
                ->withErrors(['email' => 'Could not send that email. Check the SMTP settings.']);
        }

        $order->recordEvent('email', 'Confirmation Email Resent', ['to' => $order->email]);

        AuditLog::record('emailed', 'Resent confirmation for order '.$order->number, $order);

        return back()->with('status', 'Confirmation email resent.');
    }

    public function cancel(Request $request, Order $order)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $this->checkout->cancel(
            $order,
            $request->string('reason')->toString() ?: null,
            $request->boolean('restock')
        );

        return back()->with('status', 'Order cancelled.');
    }

    /** Add a staff-only note to the timeline. */
    public function note(Request $request, Order $order)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $order->recordEvent('note', $data['message']);

        return back()->with('status', 'Note added.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();

        // Orders are never hard-deleted from the admin list — deleting paid
        // history would destroy the merchant's books. Bulk action archives.
        $count = Order::whereIn('id', $ids)->update(['status' => 'archived']);

        return back()->with('status', "Archived {$count} order(s).");
    }
}
