<?php

namespace App\Services\Payments;

use App\Models\Setting;

/**
 * Typed accessor over the DB Setting store for the Authorize.Net gateway.
 *
 * Credentials live in the settings table, never .env, per the fleet's DB-driven
 * config rule. Views never call this: controllers and composers read it and hand
 * plain values to the template. Mirrors PaymentSettings (the Stripe reader) in
 * shape and gating.
 *
 * MODE, NOT SEPARATE KEY SETS. Unlike Stripe, Authorize.Net uses one set of
 * merchant credentials (API Login ID + Transaction Key) and a mode flag that
 * only selects which endpoint host they are sent to (apitest vs api). The same
 * credentials are therefore read regardless of mode; only endpoint() branches.
 */
class AuthorizeNetSettings
{
    public const KEY_ENABLED = 'authnet_enabled';

    public const KEY_MODE = 'authnet_mode';

    public const KEY_API_LOGIN_ID = 'authnet_api_login_id';

    public const KEY_TRANSACTION_KEY = 'authnet_transaction_key';

    public const KEY_SIGNATURE_KEY = 'authnet_signature_key';

    public const KEY_PUBLIC_CLIENT_KEY = 'authnet_public_client_key';

    /** Settings that are secrets: never echoed back into a form field. */
    public const SECRET_KEYS = ['authnet_transaction_key', 'authnet_signature_key'];

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    */

    /** 'sandbox' or 'production'. Sandbox is the default, deliberately. */
    public static function mode(): string
    {
        return Setting::get(self::KEY_MODE, 'sandbox') === 'production' ? 'production' : 'sandbox';
    }

    public static function isSandbox(): bool
    {
        return self::mode() === 'sandbox';
    }

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    */

    public static function apiLoginId(): ?string
    {
        return self::nullIfBlank(Setting::get(self::KEY_API_LOGIN_ID));
    }

    public static function transactionKey(): ?string
    {
        return self::nullIfBlank(Setting::get(self::KEY_TRANSACTION_KEY));
    }

    /** Hex string used as the raw HMAC key (via hex2bin) for webhook signatures. */
    public static function signatureKey(): ?string
    {
        return self::nullIfBlank(Setting::get(self::KEY_SIGNATURE_KEY));
    }

    /** Accept.js public client key, safe to expose in the browser. */
    public static function publicClientKey(): ?string
    {
        return self::nullIfBlank(Setting::get(self::KEY_PUBLIC_CLIENT_KEY));
    }

    /*
    |--------------------------------------------------------------------------
    | Gating
    |--------------------------------------------------------------------------
    */

    /**
     * Are the credentials needed to take a card payment present?
     *
     * Both the API Login ID and the Transaction Key are required: either one on
     * its own renders a card form that cannot charge, which fails in front of a
     * shopper holding their wallet. Refusing to offer the gateway is better.
     */
    public static function isConfigured(): bool
    {
        return self::apiLoginId() !== null && self::transactionKey() !== null;
    }

    /**
     * The single gate the card gateway hangs off. Both halves are re-checked on
     * every request: if a merchant later clears a credential the card option must
     * disappear from checkout rather than stay and fail at the card field.
     */
    public static function enabled(): bool
    {
        return self::switchIsOn() && self::isConfigured();
    }

    /** Raw switch position, ignoring whether credentials back it up. */
    public static function switchIsOn(): bool
    {
        return Setting::get(self::KEY_ENABLED, '0') === '1';
    }

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    */

    /** The JSON API endpoint for the active mode. */
    public static function endpoint(): string
    {
        return self::isSandbox()
            ? (string) config('payments.authnet.sandbox_uri', 'https://apitest.authorize.net/xml/v1/request.api')
            : (string) config('payments.authnet.production_uri', 'https://api.authorize.net/xml/v1/request.api');
    }

    private static function nullIfBlank($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
