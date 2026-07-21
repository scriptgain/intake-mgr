<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the demo personas that the one-click "Demo Logins" picker signs into:
 * two staff users and four customers, the customers carrying realistic order
 * history across every state (paid, shipped, delivered, pending, refunded).
 *
 * Idempotent by design, so it is safe to re-run:
 *   - staff + customers are firstOrCreate'd on their email
 *   - a customer's orders are built only when they have none yet
 *   - order numbers come from Order::nextNumber(), so the real test orders
 *     SM-1000/1001/1002 are never touched and never duplicated
 *
 * Money is computed exactly the way checkout does it: integer cents throughout,
 * subtotal -> discount -> shipping -> tax on the discounted subtotal, with the
 * order-level discount spread across the lines via Money::allocate so the parts
 * sum to the whole. Nothing here is hand-waved.
 */
class DemoOrdersSeeder extends Seeder
{
    /** Sales-tax rate applied to demo orders, in basis points (8.6%, AZ-ish). */
    private const TAX_BPS = 860;

    /** Flat shipping, waived over this subtotal (mirrors the catalog copy). */
    private const SHIPPING_CENTS = 800;

    private const FREE_SHIPPING_OVER = 7500;

    public function run(): void
    {
        // The orders reference real catalog variants, so make sure the demo
        // catalog exists first. DemoStoreSeeder is itself idempotent.
        if (Product::count() === 0) {
            $this->call(DemoStoreSeeder::class);
        }

        $this->staff();
        $this->customersWithOrders();
    }

    /* ---- Staff personas ---------------------------------------------- */

    private function staff(): void
    {
        User::firstOrCreate(
            ['email' => 'demo-admin@example.com'],
            ['name' => 'Demo Merchant Admin', 'password' => 'demo-password-admin', 'role' => 'admin']
        );

        User::firstOrCreate(
            ['email' => 'demo-staff@example.com'],
            ['name' => 'Demo Staff', 'password' => 'demo-password-staff', 'role' => 'staff']
        );
    }

    /* ---- Customer personas + their orders ---------------------------- */

    private function customersWithOrders(): void
    {
        foreach ($this->customerSpecs() as $spec) {
            $customer = $this->ensureCustomer($spec);

            // Idempotency guard: only build history for a customer who has none.
            if ($customer->orders()->exists()) {
                continue;
            }

            foreach ($spec['orders'] as $order) {
                $this->buildOrder($customer, $order);
            }

            $customer->refreshTotals();
        }
    }

    private function ensureCustomer(array $spec): Customer
    {
        $address = [
            'first_name' => $spec['first_name'],
            'last_name' => $spec['last_name'],
            'company' => null,
            'line1' => $spec['line1'],
            'line2' => null,
            'city' => $spec['city'],
            'state' => $spec['state'],
            'postcode' => $spec['postcode'],
            'country' => 'US',
            'phone' => $spec['phone'],
        ];

        $customer = Customer::firstOrCreate(
            ['email' => $spec['email']],
            [
                'first_name' => $spec['first_name'],
                'last_name' => $spec['last_name'],
                'phone' => $spec['phone'],
                'password' => 'demo-password',
                'accepts_marketing' => true,
                'default_shipping_address' => $address,
                'default_billing_address' => $address,
            ]
        );

        // A default address row so the account "Addresses" tab is populated too.
        if (! $customer->addresses()->exists()) {
            $customer->addresses()->create($address + ['label' => 'Home', 'is_default' => true]);
        }

        return $customer;
    }

    /**
     * Build one order end to end: line items, correct money, the right status
     * combination, fulfillments, discount redemption and timeline events, all
     * back-dated so the account and admin lists read like a real store.
     */
    private function buildOrder(Customer $customer, array $spec): Order
    {
        return DB::transaction(function () use ($customer, $spec) {
            $placedAt = Carbon::now()->subDays($spec['days_ago'])->setTime(10, 24);

            // ---- Lines + subtotal ----
            $lines = [];
            $subtotal = 0;
            foreach ($spec['lines'] as [$slug, $qty]) {
                $variant = $this->variant($slug);
                $lineSubtotal = $variant->price_cents * $qty;
                $subtotal += $lineSubtotal;
                $lines[] = [
                    'variant' => $variant,
                    'qty' => $qty,
                    'subtotal' => $lineSubtotal,
                    'discount' => 0,
                    'tax' => 0,
                ];
            }

            // ---- Discount (spread across lines, parts sum to the whole) ----
            $discount = null;
            $discountTotal = 0;
            if (! empty($spec['discount_code'])) {
                $discount = Discount::where('code', $spec['discount_code'])->first();
                if ($discount && $discount->type === 'percentage') {
                    $discountTotal = Money::applyBps($subtotal, (int) $discount->value);
                    $weights = [];
                    foreach ($lines as $i => $line) {
                        $weights[$i] = $line['subtotal'];
                    }
                    foreach (Money::allocate($discountTotal, $weights) as $i => $share) {
                        $lines[$i]['discount'] = $share;
                    }
                }
            }

            $discountedSubtotal = $subtotal - $discountTotal;

            // ---- Shipping ----
            $shipping = $discountedSubtotal >= self::FREE_SHIPPING_OVER ? 0 : self::SHIPPING_CENTS;

            // ---- Tax on the discounted line amounts ----
            $tax = 0;
            foreach ($lines as $i => $line) {
                $taxable = $line['subtotal'] - $line['discount'];
                $lineTax = Money::applyBps($taxable, self::TAX_BPS);
                $lines[$i]['tax'] = $lineTax;
                $tax += $lineTax;
            }

            $total = $discountedSubtotal + $shipping + $tax;

            $address = $customer->default_shipping_address;

            $paid = in_array($spec['financial'], ['paid', 'refunded'], true);
            $refunded = $spec['financial'] === 'refunded';

            $financialStatus = match ($spec['financial']) {
                'refunded' => 'refunded',
                'paid' => 'paid',
                default => 'pending',
            };

            $order = Order::create([
                'number' => Order::nextNumber(),
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => 'open',
                'financial_status' => $financialStatus,
                'fulfillment_status' => 'unfulfilled', // corrected below
                'currency' => config('shop.currency', 'USD'),
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discountTotal,
                'shipping_cents' => $shipping,
                'tax_cents' => $tax,
                'total_cents' => $total,
                'refunded_cents' => $refunded ? $total : 0,
                'discount_id' => $discount?->id,
                'discount_code' => $discount?->code,
                'shipping_address' => $address,
                'billing_address' => $address,
                'shipping_method' => $shipping === 0 ? 'Free Shipping' : 'Standard Shipping',
                'payment_gateway' => $spec['gateway'],
                'payment_reference' => $paid ? 'DEMO-'.strtoupper(substr(md5($customer->email.$spec['days_ago']), 0, 10)) : null,
                'paid_at' => $paid ? $placedAt->copy()->addMinutes(3) : null,
                'refunded_at' => $refunded ? $placedAt->copy()->addDays(4) : null,
                'card_brand' => $spec['card'][0] ?? null,
                'card_last4' => $spec['card'][1] ?? null,
                'customer_note' => $spec['note'] ?? null,
            ]);

            // ---- Line items ----
            foreach ($lines as $line) {
                $variant = $line['variant'];
                $order->items()->create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'image_path' => $variant->product->primaryImage()?->path,
                    'quantity' => $line['qty'],
                    'unit_price_cents' => $variant->price_cents,
                    'discount_cents' => $line['discount'],
                    'tax_cents' => $line['tax'],
                    'total_cents' => $line['subtotal'] - $line['discount'],
                    'fulfilled_qty' => 0, // set by applyFulfillment
                    'requires_shipping' => $variant->product->requires_shipping,
                ]);
            }

            // ---- Timeline: placed ----
            $this->event($order, 'placed', 'Order Placed', $placedAt, ['items' => count($lines), 'total' => $total]);

            // ---- Payment ----
            if ($paid) {
                $this->event($order, 'paid', 'Payment Received', $placedAt->copy()->addMinutes(3), [
                    'gateway' => $order->payment_gateway,
                    'reference' => $order->payment_reference,
                ]);
            }

            // ---- Fulfillment ----
            $this->applyFulfillment($order, $spec['fulfillment'], $placedAt);

            // ---- Refund ----
            if ($refunded) {
                $this->event($order, 'refunded', 'Refunded '.Money::format($total), $placedAt->copy()->addDays(4), [
                    'amount' => $total,
                    'reason' => 'requested_by_customer',
                    'gateway' => $order->payment_gateway,
                    'refunded_total' => $total,
                ]);
            }

            // Back-date the record itself so it sorts correctly everywhere.
            $order->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->save();
            $order->items()->update(['created_at' => $placedAt, 'updated_at' => $placedAt]);

            // ---- Discount redemption (keeps used_count honest) ----
            if ($discount) {
                $discount->increment('used_count');
                $discount->redemptions()->create([
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'email' => $customer->email,
                    'amount_cents' => $discountTotal,
                ]);
            }

            return $order;
        });
    }

    /**
     * Ship some or all of an order and set the resulting fulfillment_status the
     * same way the model would, so the header badge always agrees with the items.
     */
    private function applyFulfillment(Order $order, string $mode, Carbon $placedAt): void
    {
        if ($mode === 'unfulfilled') {
            return; // items already fulfilled_qty 0, status already unfulfilled
        }

        $items = $order->items()->get();
        $shippedAt = $placedAt->copy()->addDays(1);

        $shipped = [];
        foreach ($items as $item) {
            // 'partial' ships one unit of the first line only; everything else
            // ships the whole order.
            $qty = $mode === 'partial'
                ? ($item->is($items->first()) ? min(1, $item->quantity) : 0)
                : $item->quantity;

            if ($qty <= 0) {
                continue;
            }

            $item->forceFill(['fulfilled_qty' => $qty])->save();
            $shipped[] = ['order_item_id' => $item->id, 'quantity' => $qty];
        }

        $fulfillmentStatus = match ($mode) {
            'delivered', 'shipped' => 'fulfilled',
            'returned' => 'returned',
            'partial' => 'partially_fulfilled',
            default => 'unfulfilled',
        };

        // A returned shipment leaves the units off the order's fulfilled tally.
        if ($mode === 'returned') {
            $order->items()->update(['fulfilled_qty' => 0]);
        }

        $order->forceFill(['fulfillment_status' => $fulfillmentStatus])->save();

        $carrier = ['UPS', 'USPS', 'FedEx'][crc32($order->number) % 3];
        $fulfillment = $order->fulfillments()->create([
            'user_id' => null,
            'status' => match ($mode) {
                'delivered' => 'delivered',
                'returned' => 'cancelled',
                default => 'shipped',
            },
            'carrier' => $carrier,
            'tracking_number' => '1Z'.strtoupper(substr(md5($order->number), 0, 14)),
            'items' => $shipped,
            'notify_customer' => true,
            'shipped_at' => $shippedAt,
        ]);
        $fulfillment->forceFill(['created_at' => $shippedAt, 'updated_at' => $shippedAt])->save();

        $label = $mode === 'returned' ? 'Shipment Returned' : 'Shipment Created';
        $this->event($order, 'fulfilled', $label, $shippedAt, ['items' => $shipped, 'carrier' => $carrier]);
    }

    private function event(Order $order, string $type, string $message, Carbon $at, array $meta = []): void
    {
        $event = $order->events()->create([
            'type' => $type,
            'message' => $message,
            'meta' => $meta ?: null,
            'user_id' => null,
        ]);
        $event->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    /** The default (or first) variant of a demo product, by slug. */
    private function variant(string $slug): ProductVariant
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        return $product->variants()->where('is_default', true)->first()
            ?? $product->variants()->orderBy('position')->firstOrFail();
    }

    /**
     * The four customer personas and their orders. ~11 orders total, spanning
     * every state the account and admin screens can show.
     */
    private function customerSpecs(): array
    {
        return [
            [
                'email' => 'ada@example.com',
                'first_name' => 'Ada', 'last_name' => 'Chen',
                'phone' => '+1 480 555 0142',
                'line1' => '1420 E Camelback Rd', 'city' => 'Phoenix', 'state' => 'AZ', 'postcode' => '85014',
                'orders' => [
                    ['days_ago' => 96, 'financial' => 'paid', 'fulfillment' => 'delivered', 'gateway' => 'stripe', 'card' => ['visa', '4242'], 'lines' => [['canvas-weekender-bag', 1], ['leather-card-wallet', 1]]],
                    ['days_ago' => 63, 'financial' => 'paid', 'fulfillment' => 'delivered', 'gateway' => 'manual', 'lines' => [['stoneware-mug-set', 1], ['linen-tea-towel-trio', 2]]],
                    ['days_ago' => 34, 'financial' => 'paid', 'fulfillment' => 'delivered', 'gateway' => 'stripe', 'card' => ['mastercard', '4444'], 'discount_code' => 'WELCOME10', 'lines' => [['machined-pen', 1], ['hardcover-dot-grid-notebook', 2]]],
                    ['days_ago' => 11, 'financial' => 'paid', 'fulfillment' => 'shipped', 'gateway' => 'stripe', 'card' => ['visa', '4242'], 'lines' => [['solid-brass-desk-lamp', 1]]],
                    ['days_ago' => 2, 'financial' => 'paid', 'fulfillment' => 'unfulfilled', 'gateway' => 'stripe', 'card' => ['visa', '4242'], 'lines' => [['insulated-travel-flask', 1], ['cast-iron-skillet', 1]]],
                ],
            ],
            [
                'email' => 'marcus.reed@example.com',
                'first_name' => 'Marcus', 'last_name' => 'Reed',
                'phone' => '+1 520 555 0188',
                'line1' => '88 N Stone Ave', 'city' => 'Tucson', 'state' => 'AZ', 'postcode' => '85701',
                'orders' => [
                    ['days_ago' => 51, 'financial' => 'paid', 'fulfillment' => 'delivered', 'gateway' => 'manual', 'lines' => [['cast-iron-skillet', 1]]],
                    ['days_ago' => 7, 'financial' => 'paid', 'fulfillment' => 'shipped', 'gateway' => 'stripe', 'card' => ['amex', '0005'], 'lines' => [['canvas-weekender-bag', 1]]],
                ],
            ],
            [
                'email' => 'nadia@example.com',
                'first_name' => 'Nadia', 'last_name' => 'Brooks',
                'phone' => '+1 602 555 0170',
                'line1' => '350 W Washington St', 'city' => 'Tempe', 'state' => 'AZ', 'postcode' => '85281',
                'orders' => [
                    ['days_ago' => 40, 'financial' => 'refunded', 'fulfillment' => 'returned', 'gateway' => 'stripe', 'card' => ['visa', '4242'], 'note' => 'Item did not fit as expected.', 'lines' => [['insulated-travel-flask', 2]]],
                    ['days_ago' => 15, 'financial' => 'paid', 'fulfillment' => 'delivered', 'gateway' => 'stripe', 'card' => ['visa', '4242'], 'lines' => [['stoneware-mug-set', 1]]],
                ],
            ],
            [
                'email' => 'theo@example.com',
                'first_name' => 'Theo', 'last_name' => 'Alvarez',
                'phone' => '+1 480 555 0199',
                'line1' => '2201 S Mill Ave', 'city' => 'Mesa', 'state' => 'AZ', 'postcode' => '85210',
                'orders' => [
                    ['days_ago' => 5, 'financial' => 'paid', 'fulfillment' => 'unfulfilled', 'gateway' => 'stripe', 'card' => ['mastercard', '4444'], 'lines' => [['machined-pen', 2]]],
                    ['days_ago' => 1, 'financial' => 'pending', 'fulfillment' => 'unfulfilled', 'gateway' => 'manual', 'note' => 'Please hold for bank transfer.', 'lines' => [['hardcover-dot-grid-notebook', 3]]],
                ],
            ],
        ];
    }
}
