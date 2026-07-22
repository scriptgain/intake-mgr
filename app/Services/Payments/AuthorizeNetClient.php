<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Authorize.Net JSON REST client.
 *
 * Deliberately NOT the authorizenet/authorizenet SDK. IntakeMGR needs three
 * operations (charge, refund/void, credential probe) and an HMAC signature check
 * on the webhook. The SDK is a large, XML-first dependency to carry into a
 * self-hosted install for that, and this project's composer.lock is
 * platform-pinned, which makes any dependency resolution here a deployment risk
 * rather than a convenience. Swapping this class for the SDK later touches
 * nothing outside this file.
 *
 * TWO ENDPOINT QUIRKS the modern JSON API forces on us and this class hides:
 *
 *  1. The response body is JSON but is prefixed with a UTF-8 BOM (and sometimes
 *     leading whitespace). json_decode chokes on the BOM, so it is stripped
 *     before decoding.
 *  2. There is no HTTP status distinction between an approved and a declined
 *     charge: both come back 200. Approval is determined from
 *     transactionResponse.responseCode == '1', and a decline carries its human
 *     reason inside transactionResponse.messages / .errors rather than as an
 *     HTTP error.
 *
 * MERCHANT MODEL: direct charges on the merchant's own Authorize.Net account.
 * IntakeMGR is self-hosted software, not a marketplace. The merchant's API Login
 * ID + Transaction Key are the only credentials, read from the DB settings store
 * via AuthorizeNetSettings, never from .env.
 *
 * NEVER THROWS. Every method returns a normalized result the caller renders, so
 * a checkout can say "we could not reach the card processor" rather than
 * white-screen. Credentials are redacted out of any surfaced error.
 */
class AuthorizeNetClient
{
    /*
    |--------------------------------------------------------------------------
    | Charging
    |--------------------------------------------------------------------------
    */

    /**
     * Charge an Accept.js opaque token (authCapture: authorize + capture in one).
     *
     * The amount arrives in cents and is always the server-side order total; it
     * is converted to decimal dollars with an integer-safe number_format so a
     * float never rounds a penny away. The card itself never reaches us: only the
     * opaqueData nonce minted by Accept.js in the browser.
     *
     * @param  array{dataDescriptor?:string,dataValue?:string}  $opaqueData
     * @param  array{order_number?:string,description?:string,email?:string}  $meta
     * @return array{ok:bool, data:array, error:?string, code:?string}
     */
    public static function charge(int $amountCents, string $currency, array $opaqueData, array $meta): array
    {
        $order = array_filter([
            'invoiceNumber' => isset($meta['order_number']) ? substr((string) $meta['order_number'], 0, 20) : null,
            'description' => isset($meta['description']) ? substr((string) $meta['description'], 0, 255) : null,
        ], fn ($v) => $v !== null && $v !== '');

        $transaction = [
            'transactionType' => 'authCaptureTransaction',
            'amount' => self::dollars($amountCents),
            'payment' => [
                'opaqueData' => [
                    'dataDescriptor' => (string) ($opaqueData['dataDescriptor'] ?? ''),
                    'dataValue' => (string) ($opaqueData['dataValue'] ?? ''),
                ],
            ],
        ];

        if ($order) {
            $transaction['order'] = $order;
        }

        if (! empty($meta['email'])) {
            $transaction['customer'] = ['email' => (string) $meta['email']];
        }

        $result = self::send('createTransactionRequest', [
            'transactionRequest' => $transaction,
        ]);

        if (! $result['ok']) {
            return $result;
        }

        return self::interpretTransaction($result['data']);
    }

    /**
     * Refund a settled transaction, falling back to a void when it is not yet
     * settled.
     *
     * Authorize.Net requires the original transaction id (refTransId) plus the
     * card's last four to issue a refund. A transaction that has NOT yet settled
     * cannot be refunded (error E00027 / "cannot be refunded"): it must be voided
     * instead. This method detects that case from the first response and retries
     * as a voidTransaction against the same refTransId, so the caller gets one
     * "money reversed" result regardless of which path the processor required.
     *
     * @return array{ok:bool, data:array, error:?string, code:?string}
     */
    public static function refundTransaction(string $transId, int $amountCents, string $last4): array
    {
        $last4 = self::last4($last4);

        $result = self::send('createTransactionRequest', [
            'transactionRequest' => [
                'transactionType' => 'refundTransaction',
                'amount' => self::dollars($amountCents),
                'payment' => [
                    'creditCard' => [
                        // Only the last four are known to us and it is all
                        // Authorize.Net wants here alongside refTransId.
                        'cardNumber' => $last4,
                        'expirationDate' => 'XXXX',
                    ],
                ],
                'refTransId' => $transId,
            ],
        ]);

        if ($result['ok']) {
            $interpreted = self::interpretTransaction($result['data']);

            if ($interpreted['ok']) {
                return $interpreted;
            }

            // Unsettled: a refund is refused, a void is the correct reversal.
            if (self::isUnsettledRefund($interpreted)) {
                return self::voidTransaction($transId);
            }

            return $interpreted;
        }

        // A transport-level failure whose code still signals "unsettled".
        if (self::isUnsettledRefund($result)) {
            return self::voidTransaction($transId);
        }

        return $result;
    }

    /** Reverse a not-yet-settled transaction in full. */
    public static function voidTransaction(string $transId): array
    {
        $result = self::send('createTransactionRequest', [
            'transactionRequest' => [
                'transactionType' => 'voidTransaction',
                'refTransId' => $transId,
            ],
        ]);

        if (! $result['ok']) {
            return $result;
        }

        return self::interpretTransaction($result['data']);
    }

    /**
     * Credential probe for the admin "test connection" button. Confirms the API
     * Login ID + Transaction Key work before a shopper is the one who finds out
     * they do not.
     *
     * @return array{ok:bool, data:array, error:?string, code:?string}
     */
    public static function authenticateTest(): array
    {
        return self::send('authenticateTestRequest', []);
    }

    /*
    |--------------------------------------------------------------------------
    | Response interpretation
    |--------------------------------------------------------------------------
    */

    /**
     * Turn a createTransactionRequest response into a normalized result.
     *
     * Approved (responseCode 1) yields the reference id, the card account type
     * (brand) and the masked account number reduced to its last four. Anything
     * else surfaces the human-readable decline/error reason, redacted.
     */
    private static function interpretTransaction(array $data): array
    {
        $tr = $data['transactionResponse'] ?? [];
        $code = (string) ($tr['responseCode'] ?? '');

        if ($code === '1') {
            return [
                'ok' => true,
                'data' => [
                    'transId' => (string) ($tr['transId'] ?? ''),
                    'accountType' => (string) ($tr['accountType'] ?? ''),
                    'last4' => self::last4((string) ($tr['accountNumber'] ?? '')),
                    'authCode' => (string) ($tr['authCode'] ?? ''),
                    'responseCode' => $code,
                ],
                'error' => null,
                'code' => null,
            ];
        }

        // Prefer the transaction-level error message (the card-actionable one),
        // then the transaction-level message, then the request-level message.
        $message = data_get($tr, 'errors.0.errorText')
            ?? data_get($tr, 'messages.0.description')
            ?? data_get($data, 'messages.message.0.text')
            ?? 'The payment was declined.';

        $errCode = data_get($tr, 'errors.0.errorCode')
            ?? data_get($data, 'messages.message.0.code')
            ?? 'declined';

        return [
            'ok' => false,
            'data' => $data,
            'error' => OrderPayments::redact((string) $message),
            'code' => (string) $errCode,
        ];
    }

    /** Does this result mean "refund refused because the txn has not settled"? */
    private static function isUnsettledRefund(array $result): bool
    {
        $code = strtoupper((string) ($result['code'] ?? ''));
        $error = strtolower((string) ($result['error'] ?? ''));

        return $code === 'E00027'
            || str_contains($error, 'cannot be refunded')
            || str_contains($error, 'has not been settled');
    }

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    */

    /**
     * POST one Authorize.Net JSON request and normalize the reply.
     *
     * The single top-level key ($operation) wraps a merchantAuthentication block
     * built from the DB credentials plus the operation-specific body. Success is
     * a resultCode of 'Ok' at messages.resultCode; a resultCode of 'Error' with
     * no transactionResponse (a request-level failure such as bad credentials) is
     * surfaced as a normalized error.
     *
     * @return array{ok:bool, data:array, error:?string, code:?string}
     */
    private static function send(string $operation, array $body): array
    {
        $login = AuthorizeNetSettings::apiLoginId();
        $key = AuthorizeNetSettings::transactionKey();

        if (! $login || ! $key) {
            return self::failure('Card payments are not configured yet.', 'not_configured');
        }

        $payload = [
            $operation => array_merge([
                'merchantAuthentication' => [
                    'name' => $login,
                    'transactionKey' => $key,
                ],
            ], $body),
        ];

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('payments.authnet.timeout', 20))
                ->post(AuthorizeNetSettings::endpoint(), $payload);

            // Authorize.Net returns 200 with a BOM-prefixed JSON body. Strip a
            // leading BOM and any surrounding whitespace before decoding, since
            // json_decode rejects the BOM and Http::json() would return null.
            $data = self::decode($response->body());

            if (! is_array($data)) {
                Log::warning('Authorize.Net response was not decodable JSON', [
                    'operation' => $operation,
                    'status' => $response->status(),
                    'bytes' => strlen($response->body()),
                ]);

                return self::failure('Could not read the card processor response. Please try again.', 'bad_response');
            }

            $resultCode = (string) data_get($data, 'messages.resultCode', '');

            // A request-level Ok (transaction interpretation happens in the
            // caller) OR any body carrying a transactionResponse is handed back
            // for interpretation. A request-level Error with no transaction is a
            // credential/validation failure and is normalized here.
            if ($resultCode === 'Ok' || isset($data['transactionResponse'])) {
                return ['ok' => true, 'data' => $data, 'error' => null, 'code' => null];
            }

            $message = data_get($data, 'messages.message.0.text', 'The card processor rejected that request.');
            $code = data_get($data, 'messages.message.0.code', 'api_error');

            Log::warning('Authorize.Net request failed', [
                'operation' => $operation,
                'status' => $response->status(),
                'code' => $code,
            ]);

            return self::failure(OrderPayments::redact((string) $message), (string) $code);
        } catch (\Throwable $e) {
            Log::warning('Authorize.Net request threw', [
                'operation' => $operation,
                'exception' => class_basename($e),
            ]);

            return self::failure('Could not reach the card processor. Please try again.', 'connection_error');
        }
    }

    /** Strip a leading UTF-8 BOM / whitespace, then json_decode to an array. */
    private static function decode(string $body): ?array
    {
        // U+FEFF as UTF-8 bytes, plus any stray leading whitespace.
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
        $body = ltrim((string) $body, " \t\n\r\0\x0B\xEF\xBB\xBF");

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Integer-safe cents -> "12.34" decimal dollars string. */
    private static function dollars(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** Reduce 'XXXX1111' / masked PAN to the bare last four digits. */
    private static function last4(string $account): string
    {
        $digits = preg_replace('/\D/', '', $account);

        return $digits === '' ? '' : substr($digits, -4);
    }

    private static function failure(string $message, ?string $code): array
    {
        return ['ok' => false, 'data' => [], 'error' => $message, 'code' => $code];
    }
}
