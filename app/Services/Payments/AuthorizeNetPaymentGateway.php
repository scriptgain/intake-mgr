<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Authorize.Net gateway: on-site Accept.js tokenization charged server-side.
 *
 * Unlike Stripe (confirmed client-side, completed via return/webhook), the card
 * is tokenized in the browser by Accept.js into an opaque nonce, posted back to
 * us, and charged synchronously here. The order state machine (financial_status,
 * idempotent settle, refunded_cents, signed pay URLs) is provider-neutral and
 * lives in OrderPayments; this class is only the transport plus the shape the pay
 * screen needs.
 *
 * THE AMOUNT IS NEVER TAKEN FROM THE CLIENT. Every charge is $order->total_cents.
 * The browser posts only the Accept.js nonce, never an amount.
 */
class AuthorizeNetPaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'authorizenet';
    }

    public function label(): string
    {
        return 'Authorize.Net';
    }

    public function isConfigured(): bool
    {
        return AuthorizeNetSettings::isConfigured();
    }

    public function isEnabled(): bool
    {
        return AuthorizeNetSettings::enabled();
    }

    /**
     * Everything the pay screen needs to render the Accept.js card form: the
     * public keys only. Never an amount, never the transaction key. Guards mirror
     * OrderPayments::clientSecretFor so an unavailable / paid / cancelled / below
     * minimum order is handled before a form is drawn.
     */
    public function startPayment(Order $order): array
    {
        if (! AuthorizeNetSettings::enabled()) {
            return $this->startFailure('Card payments are not available right now.');
        }

        if ($order->is_paid) {
            return [
                'ok' => true,
                'provider' => 'authorizenet',
                'settled' => true,
                'error' => null,
                'data' => $this->publicData(),
            ];
        }

        if ($order->is_cancelled) {
            return $this->startFailure('That order has been cancelled.');
        }

        // The one true amount. Not a form field, not a query string.
        $amountCents = (int) $order->total_cents;
        $minimum = (int) config('payments.minimum_charge_cents', 50);

        if ($amountCents < $minimum) {
            return $this->startFailure(
                'That total is below the '.Money::format($minimum).' card minimum. Please contact us to pay another way.'
            );
        }

        return [
            'ok' => true,
            'provider' => 'authorizenet',
            'settled' => false,
            'error' => null,
            'data' => $this->publicData(),
        ];
    }

    /**
     * Charge the Accept.js opaque token server-side.
     *
     * Idempotent: an already-paid order returns settled immediately, so a
     * double-submitted form or a webhook that beat the response is a no-op. On
     * approval the provider-neutral paid transition records the payment (brand +
     * last four only); on decline the order is marked failed and a shopper-safe,
     * redacted message is returned.
     *
     * @param  array{opaque_data_descriptor?:string,opaque_data_value?:string}  $input
     */
    public function completePayment(Order $order, array $input): array
    {
        if ($order->is_paid) {
            return ['ok' => true, 'settled' => true, 'error' => null];
        }

        if (! AuthorizeNetSettings::enabled()) {
            return ['ok' => false, 'settled' => false, 'error' => 'Card payments are not available right now.'];
        }

        if ($order->is_cancelled) {
            return ['ok' => false, 'settled' => false, 'error' => 'That order has been cancelled.'];
        }

        $descriptor = trim((string) ($input['opaque_data_descriptor'] ?? ''));
        $value = trim((string) ($input['opaque_data_value'] ?? ''));

        if ($descriptor === '' || $value === '') {
            return ['ok' => false, 'settled' => false, 'error' => 'The card form did not return a token. Please re-enter your card details.'];
        }

        $result = AuthorizeNetClient::charge(
            (int) $order->total_cents,
            strtoupper($order->currency ?: (string) config('shop.currency', 'USD')),
            ['dataDescriptor' => $descriptor, 'dataValue' => $value],
            [
                'order_number' => $order->number,
                'description' => Str::limit(config('shop.store_name', 'Order').' '.$order->number, 255, ''),
                'email' => $order->email,
            ]
        );

        if (! $result['ok']) {
            OrderPayments::markFailed($order, $result['error']);

            // The decline reason is card-actionable and already redacted by the
            // client. Repeat it verbatim rather than hiding a fixable problem.
            return ['ok' => false, 'settled' => false, 'error' => OrderPayments::redact($result['error'])];
        }

        $charge = $result['data'];

        OrderPayments::recordExternalPayment(
            $order,
            'authorizenet',
            (string) ($charge['transId'] ?? ''),
            [
                'brand' => $charge['accountType'] ?? null,
                'last4' => $charge['last4'] ?? null,
            ],
            ! AuthorizeNetSettings::isSandbox()
        );

        return ['ok' => true, 'settled' => true, 'error' => null];
    }

    /**
     * Authorize.Net has no cheap intent re-read like Stripe: the charge is
     * synchronous, so local state is authoritative the instant completePayment
     * returns. Nothing to reconcile here.
     */
    public function syncFromRemote(Order $order): Order
    {
        return $order;
    }

    /**
     * Refund (or void a still-unsettled charge), fully or partially.
     *
     * The amount is bounded here by what is actually refundable, computed from
     * the order, so a tampered form field cannot refund more than was charged.
     * The DB bookkeeping mirrors OrderPayments::refund exactly (row-locked
     * transaction, refunded_cents, partially_refunded|refunded, refunded event).
     *
     * @return array{ok:bool, error:?string, amount_cents:int}
     */
    public function refund(Order $order, ?int $amountCents, ?string $reason, ?User $staff): array
    {
        if (! $order->is_paid) {
            return ['ok' => false, 'error' => 'That order has not been paid.', 'amount_cents' => 0];
        }

        if (! $order->authnet_transaction_id) {
            return ['ok' => false, 'error' => 'This order has no Authorize.Net transaction to refund.', 'amount_cents' => 0];
        }

        $refundable = max(0, (int) $order->total_cents - (int) $order->refunded_cents);
        $amountCents = $amountCents === null ? $refundable : min($amountCents, $refundable);

        if ($amountCents < 1) {
            return ['ok' => false, 'error' => 'Enter a refund amount greater than zero.', 'amount_cents' => 0];
        }

        $result = AuthorizeNetClient::refundTransaction(
            (string) $order->authnet_transaction_id,
            $amountCents,
            (string) $order->card_last4
        );

        if (! $result['ok']) {
            Log::warning('Authorize.Net refund failed', [
                'order' => $order->number,
                'code' => $result['code'] ?? null,
            ]);

            // Staff see the real reason: they are trusted and can act on it.
            // Still redacted of anything credential-shaped.
            return ['ok' => false, 'error' => OrderPayments::redact($result['error']), 'amount_cents' => 0];
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

        $fresh->recordEvent('refunded', 'Refunded '.Money::format($amountCents), array_filter([
            'amount' => $amountCents,
            'reason' => $reason,
            'gateway' => 'authorizenet',
            'refunded_total' => $fresh->refunded_cents,
            // The refund's OWN Authorize.Net transaction id. Recording it lets
            // the inbound refund.created webhook recognise a refund we issued
            // ourselves and skip re-applying it (admin + webhook double count).
            'authnet_refund_id' => $result['data']['transId'] ?? null,
        ], fn ($v) => $v !== null), $staff?->id);

        rescue(fn () => $fresh->customer?->refreshTotals(), null, false);

        return ['ok' => true, 'error' => null, 'amount_cents' => $amountCents];
    }

    /**
     * Verify an inbound webhook.
     *
     * Authorize.Net signs the raw request body with HMAC-SHA512 keyed by the
     * Signature Key, delivered in X-ANET-Signature as "sha512=HEXDIGEST". The
     * Signature Key is itself a hex string used as the RAW key, so it is hex2bin
     * -decoded before hashing. Comparison is constant-time and case-insensitive
     * (the header digest may be upper or lower case).
     *
     * @return array{ok:bool, reason:?string, event:?array}
     */
    public function verifyWebhook(string $payload, array $headers): array
    {
        $signatureKey = AuthorizeNetSettings::signatureKey();

        if (! $signatureKey) {
            return ['ok' => false, 'reason' => 'no_signature_key_configured', 'event' => null];
        }

        $header = $this->header($headers, 'x-anet-signature');

        if (! $header) {
            return ['ok' => false, 'reason' => 'missing_signature_header', 'event' => null];
        }

        // Header shape: "sha512=ABCD...". Tolerate a missing scheme prefix.
        $provided = $header;
        if (str_contains($header, '=')) {
            [$scheme, $digest] = explode('=', $header, 2);
            $provided = $digest !== '' ? $digest : $scheme;
        }

        $rawKey = @hex2bin($signatureKey);

        if ($rawKey === false || $rawKey === '') {
            return ['ok' => false, 'reason' => 'invalid_signature_key', 'event' => null];
        }

        $expected = hash_hmac('sha512', $payload, $rawKey);

        // Compare case-insensitively in constant time: hash_equals over the
        // lower-cased hex of both sides so the header's case cannot leak timing.
        if (! hash_equals(strtolower($expected), strtolower(trim($provided)))) {
            return ['ok' => false, 'reason' => 'signature_mismatch', 'event' => null];
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return ['ok' => false, 'reason' => 'unparseable_body', 'event' => null];
        }

        return ['ok' => true, 'reason' => null, 'event' => $event];
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Public, browser-safe keys for the Accept.js form. Never the txn key. */
    private function publicData(): array
    {
        return [
            'api_login_id' => AuthorizeNetSettings::apiLoginId(),
            'client_key' => AuthorizeNetSettings::publicClientKey(),
            'sandbox' => AuthorizeNetSettings::isSandbox(),
        ];
    }

    private function startFailure(string $message): array
    {
        return [
            'ok' => false,
            'provider' => 'authorizenet',
            'settled' => false,
            'error' => $message,
            'data' => $this->publicData(),
        ];
    }

    /** Case-insensitive header lookup over Symfony's all() array-of-arrays. */
    private function header(array $headers, string $name): ?string
    {
        $name = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? ($value[0] ?? null) : (string) $value;
            }
        }

        return null;
    }
}
