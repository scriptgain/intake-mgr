<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a priced cart into an Order.
 *
 * Everything happens in one transaction with the variant rows locked, because
 * two shoppers racing for the last unit of stock is the failure mode that
 * matters here: without the lock both reads see qty=1, both decrement, and the
 * merchant oversells.
 */
class CheckoutService
{
    public function __construct(
        private PricingService $pricing,
        private CartService $carts,
    ) {}

    /**
     * @param  array  $data  validated checkout input: email, addresses, shipping_rate_id,
     *                       payment_gateway, customer_note, plus optional account password
     *
     * @throws ValidationException when stock ran out between the cart page and submit
     */
    public function place(Cart $cart, array $data): Order
    {
        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        return DB::transaction(function () use ($cart, $data) {
            // Lock every variant in the basket before reading stock.
            $variantIds = $cart->items->pluck('product_variant_id')->all();
            $variants = ProductVariant::whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Re-check stock under the lock. A shopper who lost the race is told
            // now, before any payment is attempted.
            foreach ($cart->items as $item) {
                $variant = $variants[$item->product_variant_id] ?? null;

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'cart' => 'An item in your cart is no longer available.',
                    ]);
                }

                $max = $variant->purchasableQuantity();
                if ($max !== null && $item->quantity > $max) {
                    throw ValidationException::withMessages([
                        'cart' => "{$item->product->name} only has {$max} left. Please adjust your cart.",
                    ]);
                }
            }

            $shipping = $data['shipping_address'] ?? null;
            $quote = $this->pricing->quote($cart, $shipping, $data['shipping_rate_id'] ?? null);

            $customer = $this->resolveCustomer($data, $cart);

            $order = Order::create([
                'number' => Order::nextNumber(),
                'customer_id' => $customer?->id,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => 'open',
                // Manual/offline gateways start unpaid; a card gateway flips this
                // once its charge confirms.
                'financial_status' => 'pending',
                'fulfillment_status' => 'unfulfilled',
                'currency' => config('shop.currency', 'USD'),
                'subtotal_cents' => $quote['subtotal_cents'],
                'discount_cents' => $quote['discount_cents'],
                'shipping_cents' => $quote['shipping_cents'],
                'tax_cents' => $quote['tax_cents'],
                'total_cents' => $quote['total_cents'],
                'discount_id' => $quote['discount']?->id,
                'discount_code' => $quote['discount']?->code,
                'shipping_address' => $shipping,
                'billing_address' => $data['billing_address'] ?? $shipping,
                'shipping_method' => $quote['shipping_rate']?->name,
                'payment_gateway' => $data['payment_gateway'] ?? config('shop.payments.default_gateway'),
                'customer_note' => $data['customer_note'] ?? null,
            ]);

            // Freeze the line items and decrement stock.
            foreach ($cart->items as $item) {
                $variant = $variants[$item->product_variant_id];
                $line = $quote['lines'][$item->id] ?? null;

                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $variant->id,
                    'name' => $item->product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'image_path' => $item->product->primaryImage()?->path,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'discount_cents' => $line['discount_cents'] ?? 0,
                    'tax_cents' => $line['tax_cents'] ?? 0,
                    'total_cents' => ($item->unit_price_cents * $item->quantity) - ($line['discount_cents'] ?? 0),
                    'requires_shipping' => $item->product->requires_shipping,
                ]);

                if ($variant->track_inventory) {
                    $variant->decrement('inventory_qty', $item->quantity);
                }
            }

            // Record the redemption so per-customer limits hold.
            if ($discount = $quote['discount']) {
                $discount->increment('used_count');
                $discount->redemptions()->create([
                    'order_id' => $order->id,
                    'customer_id' => $customer?->id,
                    'email' => $data['email'],
                    'amount_cents' => $quote['discount_cents'],
                ]);
            }

            $order->recordEvent('placed', 'Order Placed', [
                'items' => $order->items()->count(),
                'total' => $quote['total_cents'],
            ], null);

            $this->carts->markConverted($cart, $order->id);

            return $order->fresh(['items', 'events']);
        });
    }

    /**
     * Mark an order paid and write the timeline entry + customer rollups.
     * Called by the gateway callback, or by staff for a manual payment.
     */
    public function markPaid(Order $order, ?string $reference = null, ?string $gateway = null): void
    {
        if ($order->is_paid) {
            return;
        }

        $order->forceFill([
            'financial_status' => 'paid',
            'payment_reference' => $reference ?? $order->payment_reference,
            'payment_gateway' => $gateway ?? $order->payment_gateway,
            'paid_at' => now(),
        ])->save();

        $order->recordEvent('paid', 'Payment Received', [
            'gateway' => $order->payment_gateway,
            'reference' => $order->payment_reference,
        ]);

        $order->customer?->refreshTotals();
    }

    /**
     * Restock and cancel. Restocking is optional because a merchant cancelling
     * a fraudulent order usually does not want the units back on sale.
     */
    public function cancel(Order $order, ?string $reason = null, bool $restock = true): void
    {
        DB::transaction(function () use ($order, $reason, $restock) {
            if ($restock) {
                foreach ($order->items as $item) {
                    $item->variant?->increment('inventory_qty', $item->quantity - $item->fulfilled_qty);
                }
            }

            $order->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            $order->recordEvent('cancelled', 'Order Cancelled', [
                'reason' => $reason,
                'restocked' => $restock,
            ]);
        });
    }

    /**
     * Find or create the customer behind an order. Guest checkout still creates
     * a record (so order history and repeat-customer stats work) but leaves the
     * password null until they claim the account.
     */
    private function resolveCustomer(array $data, Cart $cart): ?Customer
    {
        if ($existing = auth('customer')->user()) {
            return $existing;
        }

        $shipping = $data['shipping_address'] ?? [];

        $customer = Customer::firstOrNew(['email' => $data['email']]);
        $customer->fill([
            'first_name' => $customer->first_name ?: ($shipping['first_name'] ?? null),
            'last_name' => $customer->last_name ?: ($shipping['last_name'] ?? null),
            'phone' => $customer->phone ?: ($data['phone'] ?? null),
            'accepts_marketing' => $data['accepts_marketing'] ?? $customer->accepts_marketing ?? false,
            'default_shipping_address' => $shipping ?: $customer->default_shipping_address,
        ]);

        // Optional "create an account" checkbox on the checkout form.
        if (! empty($data['password']) && ! $customer->has_account) {
            $customer->password = $data['password'];
        }

        $customer->save();

        return $customer;
    }
}
