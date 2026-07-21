<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Resolves and mutates the current visitor's cart.
 *
 * The cart token lives in a long-lived cookie rather than the session so a
 * basket survives session expiry and browser restarts. Signing in claims the
 * cart for the customer, and merges anything already saved on their account.
 */
class CartService
{
    public const COOKIE = 'shop_cart';

    private const LIFETIME_DAYS = 30;

    private ?Cart $cart = null;

    /** The visitor's cart, created on first use. */
    public function current(bool $create = true): ?Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        $token = request()->cookie(self::COOKIE);

        if ($token) {
            $this->cart = Cart::with('items.variant', 'items.product.images')
                ->where('token', $token)
                ->whereNull('converted_at')
                ->first();
        }

        if (! $this->cart && $create) {
            $this->cart = Cart::create([
                'token' => (string) Str::uuid(),
                'customer_id' => auth('customer')->id(),
                'expires_at' => now()->addDays(self::LIFETIME_DAYS),
            ]);

            Cookie::queue(
                Cookie::make(self::COOKIE, $this->cart->token, 60 * 24 * self::LIFETIME_DAYS)
            );
        }

        // A signed-in shopper always owns the cart they're using.
        if ($this->cart && auth('customer')->check() && ! $this->cart->customer_id) {
            $this->cart->update(['customer_id' => auth('customer')->id()]);
        }

        return $this->cart;
    }

    /**
     * Add a variant. Returns an error string, or null on success.
     * Stock is checked here so both the product page and any quick-add hit the
     * same rule.
     */
    public function add(ProductVariant $variant, int $quantity = 1): ?string
    {
        $quantity = max(1, $quantity);
        $cart = $this->current();

        $existing = $cart->items()->where('product_variant_id', $variant->id)->first();
        $wanted = ($existing?->quantity ?? 0) + $quantity;

        $max = $variant->purchasableQuantity();
        if ($max !== null && $wanted > $max) {
            if ($max === 0) {
                return 'That item is out of stock.';
            }

            return "Only {$max} left in stock.";
        }

        if ($existing) {
            $existing->update(['quantity' => $wanted]);
        } else {
            $cart->items()->create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
                // Captured now; checkout re-validates before charging.
                'unit_price_cents' => $variant->price_cents,
            ]);
        }

        $this->touch($cart);

        return null;
    }

    /** Set an exact quantity; 0 removes the line. */
    public function updateQuantity(CartItem $item, int $quantity): ?string
    {
        if ($quantity <= 0) {
            $item->delete();

            return null;
        }

        $max = $item->variant?->purchasableQuantity();
        if ($max !== null && $quantity > $max) {
            return "Only {$max} left in stock.";
        }

        $item->update(['quantity' => $quantity]);
        $this->touch($item->cart);

        return null;
    }

    public function remove(CartItem $item): void
    {
        $cart = $item->cart;
        $item->delete();
        $this->touch($cart);
    }

    public function applyDiscount(?string $code): void
    {
        $cart = $this->current();
        $cart->update(['discount_code' => $code ? strtoupper(trim($code)) : null]);
        $cart->refresh();
    }

    public function clear(): void
    {
        $cart = $this->current(false);
        $cart?->items()->delete();
        $cart?->update(['discount_code' => null]);
    }

    /**
     * Re-sync prices and quantities against live product data. Called when the
     * cart page loads and again at checkout, so a shopper is never charged a
     * price they didn't see and never buys stock that has since sold out.
     *
     * @return array<string> human-readable notices about what changed
     */
    public function reconcile(Cart $cart): array
    {
        $notices = [];

        foreach ($cart->items as $item) {
            $variant = $item->variant;

            // The product or variant was deleted while it sat in the basket.
            if (! $variant || ! $item->product || $item->product->status !== 'active') {
                $notices[] = ($item->product?->name ?? 'An item').' is no longer available and was removed.';
                $item->delete();

                continue;
            }

            if ($item->unit_price_cents !== $variant->price_cents) {
                $item->update(['unit_price_cents' => $variant->price_cents]);
                $notices[] = "The price of {$item->product->name} changed.";
            }

            $max = $variant->purchasableQuantity();
            if ($max !== null && $item->quantity > $max) {
                if ($max === 0) {
                    $notices[] = "{$item->product->name} sold out and was removed.";
                    $item->delete();
                } else {
                    $item->update(['quantity' => $max]);
                    $notices[] = "Only {$max} of {$item->product->name} left; the quantity was reduced.";
                }
            }
        }

        $cart->load('items.variant', 'items.product.images');

        return $notices;
    }

    /** Mark the cart converted so it drops off the abandoned-cart list. */
    public function markConverted(Cart $cart, int $orderId): void
    {
        $cart->update(['order_id' => $orderId, 'converted_at' => now()]);
        Cookie::queue(Cookie::forget(self::COOKIE));
        $this->cart = null;
    }

    private function touch(Cart $cart): void
    {
        $cart->update(['expires_at' => now()->addDays(self::LIFETIME_DAYS)]);
        $cart->load('items.variant', 'items.product.images');
    }
}
