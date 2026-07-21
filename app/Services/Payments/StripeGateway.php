<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Stripe REST client.
 *
 * Deliberately NOT the stripe/stripe-php SDK. ShopMGR needs six endpoints and an
 * HMAC signature check. The SDK is a large dependency to carry into a
 * self-hosted install for that, and this project's composer.lock is
 * platform-pinned to PHP 8.3.30 while the host CLI is 8.4, which makes any
 * dependency resolution here a deployment risk rather than a convenience.
 * Swapping this class for the SDK later touches nothing outside this file.
 *
 * MERCHANT MODEL: direct charges on the merchant's own Stripe account. ShopMGR
 * is self-hosted software, not a marketplace: there is no platform account, no
 * Connect, and no application fee. The merchant's secret key is the only
 * credential, and funds settle straight into their own balance.
 *
 * LOGGING: this class logs error codes and request ids only. Never a full
 * response body, never a request payload, never anything card-shaped. Rejections
 * log at warning or above because these installs run LOG_LEVEL=error and a
 * notice-level webhook rejection would be silently discarded.
 */
class StripeGateway
{
    /*
    |--------------------------------------------------------------------------
    | Payment intents
    |--------------------------------------------------------------------------
    */

    /**
     * Create a PaymentIntent.
     *
     * $idempotencyKey is the order's own stored key, so a retried request, a
     * double-clicked button and a re-submitted form all collapse onto one charge
     * at Stripe's end as well as ours.
     */
    public static function createPaymentIntent(array $params, string $idempotencyKey): array
    {
        return self::request('POST', '/v1/payment_intents', $params, $idempotencyKey);
    }

    public static function retrievePaymentIntent(string $id, array $expand = []): array
    {
        return self::request(
            'GET',
            '/v1/payment_intents/'.urlencode($id),
            $expand ? ['expand' => $expand] : []
        );
    }

    public static function cancelPaymentIntent(string $id): array
    {
        return self::request('POST', '/v1/payment_intents/'.urlencode($id).'/cancel');
    }

    /**
     * Update an intent's amount.
     *
     * Needed when a shopper goes back, edits their address, and the tax or
     * shipping changes: the intent must be re-pointed at the recomputed
     * server-side total rather than a stale one.
     */
    public static function updatePaymentIntent(string $id, array $params): array
    {
        return self::request('POST', '/v1/payment_intents/'.urlencode($id), $params);
    }

    /** Refund, full or partial. Amount is cents and is always server-supplied. */
    public static function createRefund(string $paymentIntentId, ?int $amountCents, string $idempotencyKey, ?string $reason = null): array
    {
        $params = ['payment_intent' => $paymentIntentId];

        if ($amountCents !== null) {
            $params['amount'] = $amountCents;
        }

        // Stripe only accepts a fixed vocabulary here; anything else 400s.
        if (in_array($reason, ['duplicate', 'fraudulent', 'requested_by_customer'], true)) {
            $params['reason'] = $reason;
        }

        return self::request('POST', '/v1/refunds', $params, $idempotencyKey);
    }

    public static function retrieveCharge(string $chargeId): array
    {
        return self::request('GET', '/v1/charges/'.urlencode($chargeId));
    }

    /**
     * Cheap credential probe for the settings screen: reads the account behind
     * the configured secret key. Confirms the key works before a shopper is the
     * one who discovers it does not.
     */
    public static function retrieveAccount(): array
    {
        return self::request('GET', '/v1/account');
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook signatures
    |--------------------------------------------------------------------------
    */

    /**
     * Verify a Stripe-Signature header.
     *
     * Rejects unsigned requests, mis-signed requests, tampered payloads (the
     * body is part of the HMAC, so any edit changes the digest), and replays
     * outside the tolerance window. Returns a reason string rather than throwing
     * so the caller can answer 400 while logging WHY without telling the caller.
     *
     * @return array{ok: bool, reason: ?string}
     */
    public static function verifySignature(string $payload, ?string $signatureHeader, ?string $secret, ?int $toleranceSeconds = null): array
    {
        $toleranceSeconds ??= (int) config('payments.stripe.webhook_tolerance', 300);

        if (! $secret) {
            return ['ok' => false, 'reason' => 'no_webhook_secret_configured'];
        }

        if (! $signatureHeader) {
            return ['ok' => false, 'reason' => 'missing_signature_header'];
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$key, $value] = $pair;
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || ! ctype_digit((string) $timestamp) || $signatures === []) {
            return ['ok' => false, 'reason' => 'malformed_signature_header'];
        }

        // Replay window. Without this a captured request stays valid forever.
        if (abs(time() - (int) $timestamp) > $toleranceSeconds) {
            return ['ok' => false, 'reason' => 'timestamp_outside_tolerance'];
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $candidate) {
            // hash_equals, not ===: constant time, so the comparison cannot be
            // used as an oracle to guess a valid signature byte by byte.
            if (hash_equals($expected, $candidate)) {
                return ['ok' => true, 'reason' => null];
            }
        }

        return ['ok' => false, 'reason' => 'signature_mismatch'];
    }

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{ok: bool, status: int, data: array, error: ?string, code: ?string}
     *
     * Never throws. A checkout that white-screens is worse than one that says
     * "we could not reach the card processor", so every failure comes back as a
     * structured result the caller renders.
     */
    public static function request(
        string $method,
        string $path,
        array $params = [],
        ?string $idempotencyKey = null
    ): array {
        $secret = PaymentSettings::secretKey();

        if (! $secret) {
            return self::failure(0, 'Card payments are not configured yet.', 'not_configured');
        }

        try {
            $request = self::client($secret, $idempotencyKey);

            $response = $method === 'GET'
                ? $request->get($path, $params)
                : $request->asForm()->post($path, $params);

            $data = $response->json() ?? [];

            if ($response->successful()) {
                return ['ok' => true, 'status' => $response->status(), 'data' => $data, 'error' => null, 'code' => null];
            }

            $error = $data['error'] ?? [];

            // Log the SHAPE of the failure, never the response body: Stripe
            // error objects can echo back billing details.
            Log::warning('Stripe API request failed', [
                'path' => $path,
                'status' => $response->status(),
                'type' => $error['type'] ?? null,
                'code' => $error['code'] ?? null,
                'decline_code' => $error['decline_code'] ?? null,
                'request_id' => $response->header('Request-Id') ?: null,
            ]);

            return self::failure(
                $response->status(),
                $error['message'] ?? 'The card processor rejected that request.',
                $error['code'] ?? ($error['type'] ?? 'api_error')
            );
        } catch (\Throwable $e) {
            Log::warning('Stripe API request threw', [
                'path' => $path,
                'exception' => class_basename($e),
            ]);

            return self::failure(0, 'Could not reach the card processor. Please try again.', 'connection_error');
        }
    }

    private static function client(string $secret, ?string $idempotencyKey): PendingRequest
    {
        $headers = ['Stripe-Version' => (string) config('payments.stripe.version')];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return Http::withToken($secret)
            ->withHeaders($headers)
            ->baseUrl((string) config('payments.stripe.base_uri'))
            ->timeout((int) config('payments.stripe.timeout', 20))
            // Retries are safe precisely because every money-moving call carries
            // an idempotency key: a retried create cannot mint a second charge.
            ->retry(2, 200, throw: false);
    }

    private static function failure(int $status, string $message, ?string $code): array
    {
        return ['ok' => false, 'status' => $status, 'data' => [], 'error' => $message, 'code' => $code];
    }
}
