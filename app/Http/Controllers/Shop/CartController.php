<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\PricingService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private CartService $carts,
        private PricingService $pricing,
    ) {}

    public function show()
    {
        $cart = $this->carts->current();

        // Re-check prices and stock every time the cart is viewed, so what the
        // shopper sees is what checkout will charge.
        $notices = $this->carts->reconcile($cart);

        return view('shop.cart', [
            'cart' => $cart,
            'quote' => $this->pricing->quote($cart),
            'notices' => $notices,
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $variant = ProductVariant::with('product')->findOrFail($data['variant_id']);

        // Never let a draft or archived product be added by posting its id.
        abort_unless($variant->product?->status === 'active', 404);

        $error = $this->carts->add($variant, (int) ($data['quantity'] ?? 1));

        if ($error) {
            return back()->withErrors(['cart' => $error]);
        }

        return $request->boolean('buy_now')
            ? redirect()->route('shop.checkout')
            : redirect()->route('shop.cart')->with('status', 'Added To Your Cart.');
    }

    public function update(Request $request, CartItem $item)
    {
        $this->authorizeItem($item);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']]);

        if ($error = $this->carts->updateQuantity($item, (int) $data['quantity'])) {
            return back()->withErrors(['cart' => $error]);
        }

        return back()->with('status', 'Cart updated.');
    }

    public function remove(CartItem $item)
    {
        $this->authorizeItem($item);
        $this->carts->remove($item);

        return back()->with('status', 'Item removed.');
    }

    public function applyDiscount(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:64']]);

        $this->carts->applyDiscount($request->string('code')->toString());

        $cart = $this->carts->current();
        $quote = $this->pricing->quote($cart);

        // The pricing layer decides eligibility; surface its reason verbatim.
        if ($quote['discount_error']) {
            $this->carts->applyDiscount(null);

            return back()->withErrors(['code' => $quote['discount_error']]);
        }

        return back()->with('status', 'Discount applied.');
    }

    public function removeDiscount()
    {
        $this->carts->applyDiscount(null);

        return back()->with('status', 'Discount removed.');
    }

    /** A shopper may only touch lines in their own cart. */
    private function authorizeItem(CartItem $item): void
    {
        abort_unless($item->cart_id === $this->carts->current()?->id, 403);
    }
}
