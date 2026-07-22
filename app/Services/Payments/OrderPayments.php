<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderMailer;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The write path for money on an order.
 *
 * Three rules govern everything in this class:
 *
 *  1. THE AMOUNT IS NEVER TAKEN FROM THE CLIENT. Every charge is
 *     $order->total_cents, which PricingService computed server-side from the
 *     cart, the chosen shipping rate and the tax rules at the moment the order
 *     was written. Nothing posted by the browser reaches Stripe as an amount,
 *     and the intent's amount is re-asserted against the order on every render
 *     of the card page, so an order edited between page loads cannot be paid at
 *     a stale total.
 *
 *  2. APPLYING A RESULT IS IDEMPOTENT. markPaid() may be called by the browser
 *     redirect and by the webhook, in either order, more than once, possibly
 *     simultaneously. It takes a row lock, re-reads the state and returns early
 *     if the work is already done. That is what stops a customer being charged
 *     or credited twice, and it is what makes the webhook-arrives-before-the-
 *     redirect race a non-event.
 *
 *  3. RAW GATEWAY STRINGS DO NOT REACH THE SHOPPER. A card decline is repeated
 *     verbatim because it is actionable. Anything else becomes a generic
 *     sentence, because "Invalid API Key provided: sk_test_51H..." is both
 *     useless to a shopper and a credential leak.
 */
class OrderPayments
{
    /*
    |--------------------------------------------------------------------------
    | Starting a card payment
    |--------------------------------------------------------------------------
    */

    /**
     * Get a usable client secret for this order's card form.
     *
     * Creates the PaymentIntent on first call and REUSES it thereafter, so a
     * shopper who reloads the payment page, hits back, or double-submits gets
     * the same intent rather than a fresh one each time. Combined with the
     * order's stored idempotency key, that is the double-charge guard on the
     * outbound side.
     *
     * @return array{ok: bool, client_secret: ?string, settled: bool, error: ?string}
     */
    public static function clientSecretFor(Order $order): array
    {
        if (! PaymentSettings::isEnabled()) {
            return self::secretFailure('Card payments are not available right now.');
        }

        if ($order->is_paid) {
            return ['ok' => true, 'client_secret' => null, 'settled' => true, 'error' => null];
        }

        if ($order->is_cancelled) {
            return self::secretFailure('That order has been cancelled.');
        }

        // The one true amount. Not a form field, not a query string.
        $amountCents = (int) $order->total_cents;
        $minimum = (int) config('payments.minimum_charge_cents', 50);

        if ($amountCents < $minimum) {
            return self::secretFailure(
                'That total is below the '.Money::format($minimum).' card minimum. Please contact us to pay another way.'
            );
        }

        if ($order->stripe_payment_intent_id) {
            return self::resumeIntent($order, $amountCents);
        }

        return self::createIntent($order, $amountCents);
    }

    /**
     * Re-point an existing intent at the current server-side total and hand back
     * its secret. Converges on the truth rather than trusting what we made
     * earlier: if the order total changed, the intent is corrected before it can
     * be confirmed at the old amount.
     */
    private static function resumeIntent(Order $order, int $amountCents): array
    {
        $result = StripeGateway::retrievePaymentIntent($order->stripe_payment_intent_id, ['latest_charge']);

        if (! $result['ok']) {
            return self::secretFailure(self::shopperMessage($result));
        }

        $intent = $result['data'];
        $status = $intent['status'] ?? '';

        // Paid while the shopper was away, or the webhook already landed:
        // converge rather than offering to charge them again.
        if ($status === 'succeeded') {
            self::markPaid($order, $intent);

            return ['ok' => true, 'client_secret' => null, 'settled' => true, 'error' => null];
        }

        $reusable = ['requires_payment_method', 'requires_confirmation', 'requires_action'];

        if (! in_array($status, $reusable, true)) {
            // Canceled, processing, or otherwise spent. Detach so the next call
            // mints a clean one instead of looping on a dead intent.
            if ($status === 'canceled') {
                $order->forceFill(['stripe_payment_intent_id' => null])->save();

                return self::createIntent($order->fresh(), $amountCents);
            }

            return self::secretFailure('That payment is still being processed. Please refresh in a moment.');
        }

        // Amount drift: the order was re-priced after the intent was made.
        if ((int) ($intent['amount'] ?? 0) !== $amountCents) {
            $updated = StripeGateway::updatePaymentIntent($order->stripe_payment_intent_id, [
                'amount' => $amountCents,
            ]);

            if (! $updated['ok']) {
                return self::secretFailure(self::shopperMessage($updated));
            }

            $intent = $updated['data'];
        }

        return [
            'ok' => true,
            'client_secret' => $intent['client_secret'] ?? null,
            'settled' => false,
            'error' => null,
        ];
    }

    private static function createIntent(Order $order, int $amountCents): array
    {
        $params = [
            'amount' => $amountCents,
            'currency' => strtolower($order->currency ?: config('shop.currency', 'USD')),
            'description' => Str::limit(config('shop.store_name', 'Order').' '.$order->number, 200, ''),
            // Lets Stripe present whatever the merchant has enabled (cards,
            // wallets, local methods) without this code enumerating them.
            'automatic_payment_methods' => ['enabled' => 'true'],
            // Our reference travels with the charge so the merchant can tie a
            // Stripe dashboard row back to an order without asking us.
            'metadata' => array_filter([
                'order_number' => $order->number,
                'order_id' => (string) $order->id,
                'source' => 'ShopMGR',
            ]),
        ];

        if ($descriptor = PaymentSettings::statementDescriptor()) {
            $params['statement_descriptor_suffix'] = $descriptor;
        }

        if ($order->email) {
            $params['receipt_email'] = $order->email;
        }

        $result = StripeGateway::createPaymentIntent($params, $order->paymentIdempotencyKey());

        if (! $result['ok']) {
            self::markFailed($order, $result['error']);

            return self::secretFailure(self::shopperMessage($result));
        }

        $intent = $result['data'];

        $order->forceFill([
            'stripe_payment_intent_id' => $intent['id'] ?? null,
            'livemode' => (bool) ($intent['livemode'] ?? false),
            'payment_gateway' => 'stripe',
            'payment_failure_reason' => null,
        ])->save();

        return [
            'ok' => true,
            'client_secret' => $intent['client_secret'] ?? null,
            'settled' => false,
            'error' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Settling
    |--------------------------------------------------------------------------
    */

    /**
     * Pull the authoritative state from Stripe and apply it.
     *
     * Used by the browser return handler after a redirect (including the 3DS
     * bounce). The webhook may well have got there first; both paths funnel into
     * the same idempotent appliers, so whichever arrives second is a no-op.
     */
    public static function syncFromStripe(Order $order): Order
    {
        if (! $order->stripe_payment_intent_id || $order->is_paid) {
            return $order;
        }

        // expand latest_charge: card brand and last4 live on the charge object,
        // not on the intent.
        $result = StripeGateway::retrievePaymentIntent($order->stripe_payment_intent_id, ['latest_charge']);

        if (! $result['ok']) {
            return $order;
        }

        return self::applyIntent($order, $result['data']);
    }

    /** Route an intent object to the right terminal handler. */
    public static function applyIntent(Order $order, array $intent): Order
    {
        return match ($intent['status'] ?? '') {
            'succeeded' => self::markPaid($order, $intent),
            'canceled' => self::markVoided($order),
            'requires_payment_method' => self::markFailed(
                $order,
                data_get($intent, 'last_payment_error.message', 'The card was declined.')
            ),
            // requires_action / processing are in-flight, not terminal. Leaving
            // the order pending is correct: the webhook will finish it.
            default => $order,
        };
    }

    /**
     * Apply a successful charge. Safe to call repeatedly and concurrently.
     *
     * The row lock plus the re-read is the double-post guard: if the webhook and
     * the browser redirect land at the same instant, one of them blocks, then
     * wakes to find the work already done and returns without writing.
     */
    public static function markPaid(Order $order, array $intent = []): Order
    {
        $didTransition = false;

        DB::transaction(function () use ($order, $intent, &$didTransition) {
            /** @var Order|null $locked */
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->is_paid) {
                return; // Already applied by the other path.
            }

            $charge = is_array($intent['latest_charge'] ?? null) ? $intent['latest_charge'] : [];
            $card = data_get($charge, 'payment_method_details.card', []);

            $locked->forceFill(array_filter([
                'financial_status' => 'paid',
                'paid_at' => now(),
                'payment_gateway' => $locked->payment_gateway ?: 'stripe',
                'payment_reference' => $intent['id'] ?? $locked->payment_reference,
                'stripe_charge_id' => $charge['id'] ?? null,
                // Brand and last four only. Never the PAN, never the token.
                'card_brand' => $card['brand'] ?? null,
                'card_last4' => $card['last4'] ?? null,
                'livemode' => $intent['livemode'] ?? $locked->livemode,
                'payment_failure_reason' => null,
            ], fn ($v) => $v !== null))->save();

            $didTransition = true;

            $order->setRawAttributes($locked->getAttributes(), true);
        });

        $fresh = $order->fresh();

        if (! $didTransition) {
            return $fresh;
        }

        // Only the transition writes a timeline entry, so a redelivered webhook
        // cannot litter the order with duplicate "Payment Received" rows.
        $fresh->recordEvent('paid', 'Payment Received', array_filter([
            'gateway' => $fresh->payment_gateway,
            'reference' => $fresh->payment_reference,
            'card' => $fresh->card_brand ? $fresh->card_brand.' ****'.$fresh->card_last4 : null,
            'livemode' => $fresh->livemode,
        ]), null);

        // Everything past this point is best effort. The customer's money has
        // already moved; a mail failure or a stats hiccup must never surface as
        // an error on their confirmation page or bounce a webhook into retry.
        rescue(fn () => $fresh->customer?->refreshTotals(), null, false);
        rescue(fn () => OrderMailer::sendForPaidOrder($fresh), null, false);

        return $fresh;
    }

    /**
     * Provider-neutral paid transition, for gateways that charge server-side
     * (Authorize.Net Accept.js) rather than being confirmed by Stripe. Same
     * idempotent row-lock + re-read guard as markPaid, so a webhook and the
     * synchronous charge response cannot both settle the order twice.
     *
     * @param array{brand?:string,last4?:string} $card
     */
    public static function recordExternalPayment(Order $order, string $provider, string $reference, array $card = [], bool $livemode = false): Order
    {
        $didTransition = false;

        DB::transaction(function () use ($order, $provider, $reference, $card, $livemode, &$didTransition) {
            /** @var Order|null $locked */
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->is_paid) {
                return;
            }

            $locked->forceFill(array_filter([
                'financial_status' => 'paid',
                'paid_at' => now(),
                'payment_gateway' => $provider,
                'payment_provider' => $provider,
                'payment_reference' => $reference,
                'authnet_transaction_id' => $provider === 'authorizenet' ? $reference : null,
                'card_brand' => $card['brand'] ?? null,
                'card_last4' => $card['last4'] ?? null,
                'livemode' => $livemode,
                'payment_failure_reason' => null,
            ], fn ($v) => $v !== null))->save();

            $didTransition = true;
            $order->setRawAttributes($locked->getAttributes(), true);
        });

        $fresh = $order->fresh();

        if (! $didTransition) {
            return $fresh;
        }

        $fresh->recordEvent('paid', 'Payment Received', array_filter([
            'gateway' => $fresh->payment_provider,
            'reference' => $fresh->payment_reference,
            'card' => $fresh->card_brand ? $fresh->card_brand.' ****'.$fresh->card_last4 : null,
            'livemode' => $fresh->livemode,
        ]), null);

        rescue(fn () => $fresh->customer?->refreshTotals(), null, false);
        rescue(fn () => OrderMailer::sendForPaidOrder($fresh), null, false);

        return $fresh;
    }

    public static function markFailed(Order $order, ?string $reason = null): Order
    {
        // A late failure event must never undo a success. Stripe can emit a
        // payment_failed for an earlier attempt after a later attempt succeeded.
        if ($order->is_paid) {
            return $order;
        }

        $order->forceFill([
            'financial_status' => 'failed',
            'payment_failure_reason' => self::redact($reason ?: 'The payment did not complete.'),
        ])->save();

        $order->recordEvent('payment_failed', 'Payment Failed', [
            'reason' => $order->payment_failure_reason,
        ], null);

        return $order->fresh();
    }

    public static function markVoided(Order $order): Order
    {
        if ($order->is_paid) {
            return $order;
        }

        $order->forceFill(['financial_status' => 'voided'])->save();

        return $order->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Refunds
    |--------------------------------------------------------------------------
    */

    /**
     * Refund a card payment, fully or partially.
     *
     * The amount is bounded here by what is actually refundable, computed from
     * the order, so a tampered form field cannot refund more than was charged.
     *
     * @return array{ok: bool, error: ?string, amount_cents: int}
     */
    public static function refund(Order $order, ?int $amountCents, ?string $reason = null, ?User $staff = null): array
    {
        if (! $order->is_paid) {
            return ['ok' => false, 'error' => 'That order has not been paid.', 'amount_cents' => 0];
        }

        $refundable = max(0, (int) $order->total_cents - (int) $order->refunded_cents);
        $amountCents = $amountCents === null ? $refundable : min($amountCents, $refundable);

        if ($amountCents < 1) {
            return ['ok' => false, 'error' => 'Enter a refund amount greater than zero.', 'amount_cents' => 0];
        }

        // A card order goes to Stripe. A manual/offline order is bookkeeping
        // only: the merchant moved the money themselves.
        $viaStripe = $order->payment_gateway === 'stripe' && $order->stripe_payment_intent_id;

        if ($viaStripe) {
            // A key distinct per refund attempt, so two different partial
            // refunds are not collapsed into one by Stripe, while a retry of
            // the SAME refund still is.
            $idempotencyKey = 'refund-'.$order->id.'-'.$order->refunded_cents.'-'.$amountCents;

            $result = StripeGateway::createRefund(
                $order->stripe_payment_intent_id,
                $amountCents,
                $idempotencyKey,
                $reason
            );

            if (! $result['ok']) {
                Log::warning('Stripe refund failed', [
                    'order' => $order->number,
                    'code' => $result['code'],
                    'status' => $result['status'],
                ]);

                // Staff see the real reason: they can act on it, and they are
                // trusted. Still redacted of anything credential-shaped.
                return ['ok' => false, 'error' => self::redact($result['error']), 'amount_cents' => 0];
            }
        }

        DB::transaction(function () use ($order, $amountCents) {
            /** @var Order $locked */
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

            $refunded = (int) $locked->refunded_cents + $amountCents;

            $locked->forceFill([
                'refunded_cents' => $refunded,
                'financial_status' => $refunded >= (int) $locked->total_cents ? 'refunded' : 'partially_refunded',
                'refunded_at' => now(),
            ])->save();
        });

        $fresh = $order->fresh();

        $fresh->recordEvent('refunded', 'Refunded '.Money::format($amountCents), [
            'amount' => $amountCents,
            'reason' => $reason,
            'gateway' => $viaStripe ? 'stripe' : 'manual',
            'refunded_total' => $fresh->refunded_cents,
        ], $staff?->id);

        rescue(fn () => $fresh->customer?->refreshTotals(), null, false);

        return ['ok' => true, 'error' => null, 'amount_cents' => $amountCents];
    }

    /*
    |--------------------------------------------------------------------------
    | Turning gateway failures into something safe to show
    |--------------------------------------------------------------------------
    */

    /**
     * A card decline is worth repeating verbatim: "your card was declined for
     * insufficient funds" is exactly what the shopper needs to hear, and hiding
     * it behind a generic message makes a fixable problem look like a broken
     * site.
     *
     * A configuration or connectivity failure is the opposite. It tells the
     * shopper nothing they can act on, reads as though their order is at fault,
     * and in Stripe's phrasing can echo back a string shaped like an API key.
     * Those become a fixed sentence here; the real reason goes to the log and to
     * the order record where staff can see it.
     */
    public static function shopperMessage(array $result): string
    {
        $cardProblems = [
            'card_declined', 'expired_card', 'incorrect_cvc', 'incorrect_number',
            'insufficient_funds', 'invalid_expiry_month', 'invalid_expiry_year',
            'incorrect_zip', 'card_error', 'processing_error', 'card_not_supported',
            'currency_not_supported', 'authentication_required',
        ];

        if (in_array((string) $result['code'], $cardProblems, true)) {
            return self::redact((string) $result['error']);
        }

        Log::warning('Card payment could not be started', [
            'code' => $result['code'],
            'status' => $result['status'],
        ]);

        return 'We could not take that payment right now. Please try again in a few minutes, '
            .'or choose another payment method. Your card has not been charged.';
    }

    /**
     * Strip anything credential-shaped out of a message before it is stored or
     * displayed.
     *
     * Stripe already redacts its own keys in most paths, but a string that
     * reaches a database column and a web page must not depend on an upstream
     * service continuing to be careful on our behalf. This is the control that
     * would have caught the API-key fragment that leaked through the sibling
     * implementation.
     */
    public static function redact(?string $message): string
    {
        $message = (string) $message;

        $message = preg_replace('/\b(sk|pk|rk)_(test|live)_[A-Za-z0-9*_]+/', '[redacted key]', $message);
        $message = preg_replace('/\bwhsec_[A-Za-z0-9*_]+/', '[redacted secret]', $message);
        // Bare card-shaped digit runs, in case a message ever quotes one back.
        $message = preg_replace('/\b(?:\d[ -]*?){13,19}\b/', '[redacted number]', $message);

        return Str::limit($message, 250, '');
    }

    private static function secretFailure(string $message): array
    {
        return ['ok' => false, 'client_secret' => null, 'settled' => false, 'error' => $message];
    }
}
