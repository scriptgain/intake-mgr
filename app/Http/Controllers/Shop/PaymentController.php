<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\OrderPayments;
use App\Services\Payments\PaymentSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The card step of checkout.
 *
 * Split out of CheckoutController on purpose: the order already exists by the
 * time a shopper reaches here, so this controller only ever moves an existing
 * order between payment states. It never creates one, never prices one, and
 * never accepts an amount.
 *
 * Both pages are reached by a signed URL. That is what lets a guest with no
 * account get back to their own payment page after a 3D Secure bounce, while
 * making the URL useless to anyone who did not receive it: the order number is
 * in the path, so without a signature it would be trivially walkable.
 */
class PaymentController extends Controller
{
    /** The card form. */
    public function show(Request $request, Order $order)
    {
        if ($order->is_paid) {
            return redirect($this->confirmationUrl($order));
        }

        if ($order->is_cancelled) {
            return redirect()->route('shop.home')->with('warning', 'That order has been cancelled.');
        }

        /*
         * A refunded or voided order is finished. is_paid is false for both, so
         * without this an old signed payment link would still render a card
         * form and let someone pay for an order that has already been unwound.
         */
        if (in_array($order->financial_status, ['refunded', 'voided'], true)) {
            return redirect()->route('shop.home')->with('warning', 'That order is closed and cannot be paid.');
        }

        // Creates the intent on first visit, reuses it on every reload, and
        // re-asserts the amount against the order's server-computed total.
        $secret = OrderPayments::clientSecretFor($order);

        // Already settled (webhook won the race while the page was loading).
        if ($secret['settled']) {
            return redirect($this->confirmationUrl($order->fresh()));
        }

        return view('shop.payment', [
            'order' => $order->fresh('items'),
            'clientSecret' => $secret['client_secret'],
            'publishableKey' => PaymentSettings::publishableKey(),
            'paymentError' => $secret['error'],
            'isTestMode' => PaymentSettings::isTestMode(),
            'returnUrl' => $this->returnUrl($order),
            'accent' => config('brand.accent', '#ea580c'),
        ]);
    }

    /**
     * Where Stripe sends the browser after confirmPayment, including after a
     * 3D Secure challenge on the issuer's own domain.
     *
     * The query parameters Stripe appends are NOT trusted for anything. They are
     * not read to decide the outcome: the order is re-synced from the Stripe API
     * by its stored intent id, so a shopper who edits redirect_status=succeeded
     * into the URL gets an unpaid order and a card form, not a free order.
     */
    public function return(Request $request, Order $order)
    {
        if ($order->is_paid) {
            return redirect($this->confirmationUrl($order));
        }

        // Authoritative read from Stripe. The webhook may already have settled
        // this; syncFromStripe and markPaid are both idempotent, so whichever
        // path arrives second does nothing.
        $order = OrderPayments::syncFromStripe($order);

        if ($order->is_paid) {
            return redirect($this->confirmationUrl($order));
        }

        // Still in flight. Common with slower 3DS and bank redirects: Stripe has
        // returned the browser before the intent finished processing. Send them
        // to the confirmation page, which shows a pending state and reconciles
        // itself; the webhook will finish the job.
        if ($order->financial_status === 'pending') {
            return redirect($this->confirmationUrl($order))
                ->with('warning', 'Your payment is still being confirmed. This page will update once it completes.');
        }

        return redirect($this->paymentUrl($order))->with(
            'warning',
            $order->payment_failure_reason ?: 'That payment did not complete. Please try again.'
        );
    }

    private function paymentUrl(Order $order): string
    {
        return URL::temporarySignedRoute('shop.checkout.payment', now()->addDay(), ['order' => $order->number]);
    }

    private function returnUrl(Order $order): string
    {
        return URL::temporarySignedRoute('shop.checkout.return', now()->addDay(), ['order' => $order->number]);
    }

    private function confirmationUrl(Order $order): string
    {
        return URL::temporarySignedRoute(
            'shop.checkout.confirmation',
            now()->addDays(30),
            ['order' => $order->number]
        );
    }
}
