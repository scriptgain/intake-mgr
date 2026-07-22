<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The card step of paying an invoice.
 *
 * Provider-agnostic: it resolves the order's gateway (Stripe or Authorize.Net)
 * through PaymentGatewayManager and renders that provider's form. It never
 * creates an order, never prices one, and never accepts an amount — the amount
 * is always the order's server-computed total.
 *
 * Every page is reached by a signed URL so a guest returns to their OWN payment
 * page (e.g. after a 3D Secure bounce) while the order number in the path stays
 * un-walkable by anyone who did not receive the link.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $gateways)
    {
    }

    /** The card form. */
    public function show(Request $request, Order $order)
    {
        if ($order->is_paid) {
            return redirect($this->confirmationUrl($order));
        }

        if ($order->is_cancelled) {
            return redirect()->route('shop.home')->with('warning', 'That invoice has been cancelled.');
        }

        if (in_array($order->financial_status, ['refunded', 'voided'], true)) {
            return redirect()->route('shop.home')->with('warning', 'That invoice is closed and cannot be paid.');
        }

        $gateway = $this->gateways->for($order);

        // No card gateway enabled: render the manual "by arrangement" state.
        if (! $gateway || ! $gateway->isEnabled()) {
            return view('shop.payment', array_merge($this->baseView($order), [
                'provider' => 'manual',
                'paymentError' => null,
            ]));
        }

        $start = $gateway->startPayment($order);

        // Already settled (a webhook won the race while the page was loading).
        if (! empty($start['settled'])) {
            return redirect($this->confirmationUrl($order->fresh()));
        }

        $data = $start['data'] ?? [];

        return view('shop.payment', array_merge($this->baseView($order), [
            'provider' => $gateway->key(),
            'paymentError' => $start['error'] ?? null,
            'isTestMode' => (bool) ($data['test_mode'] ?? ($data['sandbox'] ?? false)),
            // Stripe.
            'clientSecret' => $data['client_secret'] ?? null,
            'publishableKey' => $data['publishable_key'] ?? null,
            // Authorize.Net.
            'authnet' => $data,
            'authnetChargeUrl' => $this->authnetChargeUrl($order),
        ]));
    }

    /**
     * Authorize.Net on-site charge. Accept.js has tokenised the card in the
     * browser; only the opaque nonce arrives here. The amount charged is the
     * order's server total, never anything in this request. Answers JSON to the
     * Accept.js fetch, or a redirect to a plain form post.
     */
    public function authnetCharge(Request $request, Order $order)
    {
        $wantsJson = $request->ajax() || $request->wantsJson();

        if ($order->is_paid) {
            $url = $this->confirmationUrl($order);

            return $wantsJson ? response()->json(['ok' => true, 'redirect' => $url]) : redirect($url);
        }

        if ($order->is_cancelled || in_array($order->financial_status, ['refunded', 'voided'], true)) {
            $msg = 'That invoice can no longer be paid.';

            return $wantsJson
                ? response()->json(['ok' => false, 'error' => $msg], 422)
                : redirect()->route('shop.home')->with('warning', $msg);
        }

        $data = $request->validate([
            'opaque_data_descriptor' => ['required', 'string', 'max:128'],
            'opaque_data_value' => ['required', 'string', 'max:8192'],
        ]);

        $gateway = $this->gateways->get('authorizenet');

        if (! $gateway || ! $gateway->isEnabled()) {
            $msg = 'Card payments are not available right now.';

            return $wantsJson
                ? response()->json(['ok' => false, 'error' => $msg], 422)
                : redirect($this->paymentUrl($order))->with('warning', $msg);
        }

        $result = $gateway->completePayment($order, $data);

        if (! empty($result['settled'])) {
            $url = $this->confirmationUrl($order->fresh());

            return $wantsJson ? response()->json(['ok' => true, 'redirect' => $url]) : redirect($url);
        }

        $msg = $result['error'] ?: 'That payment did not complete. Please try again.';

        return $wantsJson
            ? response()->json(['ok' => false, 'error' => $msg], 422)
            : redirect($this->paymentUrl($order))->with('warning', $msg);
    }

    /**
     * Stripe's return_url. The query parameters Stripe appends are NOT trusted:
     * the order is re-synced from the provider by its stored reference, so a
     * hand-edited redirect_status achieves nothing.
     */
    public function return(Request $request, Order $order)
    {
        if ($order->is_paid) {
            return redirect($this->confirmationUrl($order));
        }

        $gateway = $this->gateways->for($order);
        $order = $gateway ? $gateway->syncFromRemote($order) : $order;

        if ($order->is_paid) {
            return redirect($this->confirmationUrl($order));
        }

        if ($order->financial_status === 'pending') {
            return redirect($this->confirmationUrl($order))
                ->with('warning', 'Your payment is still being confirmed. This page will update once it completes.');
        }

        return redirect($this->paymentUrl($order))->with(
            'warning',
            $order->payment_failure_reason ?: 'That payment did not complete. Please try again.'
        );
    }

    private function baseView(Order $order): array
    {
        return [
            'order' => $order->fresh('items'),
            'returnUrl' => $this->returnUrl($order),
            'accent' => config('brand.accent', '#ea580c'),
            // Provider-neutral defaults; show() overrides the ones its provider needs.
            'clientSecret' => null,
            'publishableKey' => null,
            'isTestMode' => false,
            'authnet' => [],
            'authnetChargeUrl' => $this->authnetChargeUrl($order),
        ];
    }

    private function paymentUrl(Order $order): string
    {
        return URL::temporarySignedRoute('shop.checkout.payment', now()->addDay(), ['order' => $order->number]);
    }

    private function authnetChargeUrl(Order $order): string
    {
        return URL::temporarySignedRoute('shop.checkout.authnet-charge', now()->addDay(), ['order' => $order->number]);
    }

    private function returnUrl(Order $order): string
    {
        return URL::temporarySignedRoute('shop.checkout.return', now()->addDay(), ['order' => $order->number]);
    }

    private function confirmationUrl(Order $order): string
    {
        return URL::temporarySignedRoute('shop.checkout.confirmation', now()->addDays(30), ['order' => $order->number]);
    }
}
