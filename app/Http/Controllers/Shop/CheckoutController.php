<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $carts,
        private PricingService $pricing,
        private CheckoutService $checkout,
    ) {}

    public function show(Request $request)
    {
        $cart = $this->carts->current();

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop.cart')->with('warning', 'Your cart is empty.');
        }

        $this->carts->reconcile($cart);

        $customer = auth('customer')->user();

        // A store that has disabled guest checkout sends anonymous shoppers to
        // sign in first, preserving where they were headed.
        if (! $customer && ! config('shop.guest_checkout')) {
            return redirect()->route('shop.account.login')
                ->with('warning', 'Please sign in to complete your order.');
        }

        // Prefill from the signed-in customer's saved address.
        $prefill = $customer?->default_shipping_address ?? [];

        return view('shop.checkout', [
            'cart' => $cart,
            'quote' => $this->pricing->quote($cart, $prefill ?: null),
            'customer' => $customer,
            'prefill' => $prefill,
            'gateways' => $this->enabledGateways(),
        ]);
    }

    /**
     * Re-price after the shopper edits their address or picks a shipping rate.
     * Returns JSON for the checkout page's Alpine component — it is the only
     * way the totals panel ever changes, so cart and charge cannot diverge.
     */
    public function quote(Request $request)
    {
        $data = $request->validate([
            'shipping_address' => ['nullable', 'array'],
            'shipping_rate_id' => ['nullable', 'integer'],
        ]);

        $cart = $this->carts->current();
        $quote = $this->pricing->quote(
            $cart,
            $data['shipping_address'] ?? null,
            $data['shipping_rate_id'] ?? null
        );

        return response()->json([
            'totals' => $quote['formatted'],
            'rates' => $quote['shipping_rates']->map(fn ($rate) => [
                'id' => $rate->id,
                'name' => $rate->name,
                'description' => $rate->description,
                'price' => $rate->priceFor($quote['subtotal_cents'] - $quote['discount_cents']) === 0
                    ? 'Free'
                    : $rate->price_formatted,
            ])->values(),
            'selected_rate_id' => $quote['shipping_rate']?->id,
            'discount_error' => $quote['discount_error'],
        ]);
    }

    public function place(Request $request)
    {
        $cart = $this->carts->current();

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop.cart')->with('warning', 'Your cart is empty.');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'shipping_address.first_name' => ['required', 'string', 'max:255'],
            'shipping_address.last_name' => ['required', 'string', 'max:255'],
            'shipping_address.company' => ['nullable', 'string', 'max:255'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:255'],
            'shipping_address.state' => ['nullable', 'string', 'max:64'],
            'shipping_address.postcode' => ['nullable', 'string', 'max:32'],
            'shipping_address.country' => ['required', 'string', 'size:2'],
            'shipping_rate_id' => ['nullable', 'integer', 'exists:shipping_rates,id'],
            'payment_gateway' => ['required', Rule::in(array_keys($this->enabledGateways()))],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'string', 'min:8'],
            'terms' => [config('shop.terms_required') ? 'accepted' : 'nullable'],
        ]);

        // Billing defaults to shipping unless the shopper unticked "same as".
        $billing = $request->boolean('billing_same', true)
            ? $data['shipping_address']
            : $request->input('billing_address', $data['shipping_address']);

        $order = $this->checkout->place($cart, $data + [
            'billing_address' => $billing,
            'accepts_marketing' => $request->boolean('accepts_marketing'),
        ]);

        /*
         * A card order goes to the payment step. The order exists and is
         * pending; no money has moved yet, and the amount that will be charged
         * is the one PricingService just wrote to the order, not anything the
         * browser posted.
         *
         * Note the cart was marked converted inside place(), so a double
         * submitted checkout form finds an empty cart on its second pass and is
         * bounced above rather than minting a second order. That is the first of
         * the two double-charge guards; the order's Stripe idempotency key is
         * the second.
         */
        if ($order->payment_gateway === 'stripe') {
            return redirect($this->paymentUrl($order));
        }

        // A manual/offline gateway has nothing to charge: the merchant marks it
        // paid when the transfer lands. The customer still gets their
        // confirmation and their payment instructions now.
        \App\Services\OrderMailer::sendForPlacedOrder($order);

        return redirect($this->confirmationUrl($order));
    }

    public function confirmation(Order $order)
    {
        /*
         * Reconcile on arrival. A shopper can land here while their intent is
         * still processing (slow 3DS, a bank redirect) and before the webhook
         * has been delivered. One authoritative read from Stripe means the page
         * shows the true state rather than a stale "pending" that only corrects
         * itself on a manual refresh minutes later.
         */
        if ($order->has_card_payment && ! $order->is_paid && $order->financial_status === 'pending') {
            $order = \App\Services\Payments\OrderPayments::syncFromStripe($order);
        }

        $order->load('items');

        return view('shop.confirmation', [
            'order' => $order,
            'instructions' => $order->payment_gateway === 'manual'
                ? \App\Models\Setting::get('manual_instructions')
                : null,
            // Drives the "we are still confirming your payment" state.
            'awaitingPayment' => $order->has_card_payment && ! $order->is_paid && $order->financial_status === 'pending',
            'isTestPayment' => $order->is_test_payment,
        ]);
    }

    /** Signed link to the card step. Guests reach it without an account. */
    private function paymentUrl(Order $order): string
    {
        return URL::temporarySignedRoute(
            'shop.checkout.payment',
            now()->addDay(),
            ['order' => $order->number]
        );
    }

    /**
     * A temporary signed URL: a guest can reach their own confirmation without
     * an account, but the link cannot be edited to read another order.
     */
    private function confirmationUrl(Order $order): string
    {
        return URL::temporarySignedRoute(
            'shop.checkout.confirmation',
            now()->addDays(30),
            ['order' => $order->number]
        );
    }

    /** Gateways the merchant has switched on, in display order. */
    private function enabledGateways(): array
    {
        $settings = \App\Models\Setting::map();

        $gateways = [];

        if (($settings['manual_enabled'] ?? '1') === '1') {
            $gateways['manual'] = [
                'label' => 'Manual / Offline Payment',
                'description' => $settings['manual_instructions'] ?? 'Pay by bank transfer. We will email instructions.',
                'test_mode' => false,
            ];
        }

        /*
         * PaymentSettings::isEnabled() re-checks the switch AND the credentials
         * on every request. A merchant who clears a key later must see the card
         * option disappear from checkout, rather than have it stay on the page
         * and fail in front of a shopper holding their wallet.
         */
        if (\App\Services\Payments\PaymentSettings::isEnabled()) {
            $gateways['stripe'] = [
                'label' => 'Credit Card',
                'description' => \App\Services\Payments\PaymentSettings::isTestMode()
                    ? 'Test mode. No real payment will be taken.'
                    : 'Paid securely by card. We never see your card details.',
                'test_mode' => \App\Services\Payments\PaymentSettings::isTestMode(),
            ];
        }

        // A store with nothing configured still has to be able to take an order.
        return $gateways ?: ['manual' => [
            'label' => 'Manual / Offline Payment',
            'description' => 'Pay by bank transfer. We will email instructions.',
            'test_mode' => false,
        ]];
    }
}
