<?php

namespace App\Services\Payments;

use App\Models\Setting;

/**
 * Typed accessor over the DB Setting store for the Stripe gateway.
 *
 * Credentials live in the settings table, never .env, per the fleet's DB-driven
 * config rule. Views never call this: controllers and composers read it and hand
 * plain values to the template.
 *
 * TEST AND LIVE ARE FULLY SEPARATE KEY SETS. Separate publishable key, secret
 * key and webhook secret per mode, because a Stripe test account and a Stripe
 * live account are genuinely different accounts. Flipping the mode switch can
 * therefore never cause a test key to be used against real money or the reverse,
 * and it never destroys the other mode's credentials.
 *
 * Legacy note: an earlier build stored a single un-suffixed `stripe_secret_key`
 * / `stripe_publishable_key` / `stripe_webhook_secret`. Reads fall back to those
 * so an install configured before this module keeps working; the first save from
 * the settings screen migrates them into the mode-suffixed keys.
 */
class PaymentSettings
{
    public const KEY_ENABLED = 'stripe_enabled';

    public const KEY_MODE = 'stripe_mode';

    /** Settings that are secrets: never echoed back into a form field. */
    public const SECRET_SUFFIXES = ['secret_key', 'webhook_secret'];

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    */

    /** 'test' or 'live'. Test is the default, deliberately. */
    public static function mode(): string
    {
        return Setting::get(self::KEY_MODE, 'test') === 'live' ? 'live' : 'test';
    }

    public static function isTestMode(): bool
    {
        return self::mode() === 'test';
    }

    /**
     * Read a credential for the ACTIVE mode.
     * secretKey() -> stripe_test_secret_key, falling back to stripe_secret_key.
     */
    public static function forMode(string $suffix, $default = null)
    {
        $scoped = Setting::get('stripe_'.self::mode().'_'.$suffix);

        if (self::filled($scoped)) {
            return $scoped;
        }

        // Legacy single-slot key. Only honoured in test mode, so a pre-existing
        // un-suffixed key can never be picked up as a LIVE credential by
        // accident when someone flips the mode switch.
        if (self::isTestMode() && self::filled($legacy = Setting::get('stripe_'.$suffix))) {
            return $legacy;
        }

        return $default;
    }

    public static function putForMode(string $suffix, ?string $value): void
    {
        Setting::put('stripe_'.self::mode().'_'.$suffix, $value);
    }

    /** Write into an explicit mode, used by the settings screen. */
    public static function putForExplicitMode(string $mode, string $suffix, ?string $value): void
    {
        $mode = $mode === 'live' ? 'live' : 'test';

        Setting::put('stripe_'.$mode.'_'.$suffix, $value);
    }

    public static function hasCredential(string $mode, string $suffix): bool
    {
        $mode = $mode === 'live' ? 'live' : 'test';

        if (self::filled(Setting::get('stripe_'.$mode.'_'.$suffix))) {
            return true;
        }

        return $mode === 'test' && self::filled(Setting::get('stripe_'.$suffix));
    }

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    */

    public static function secretKey(): ?string
    {
        return self::nullIfBlank(self::forMode('secret_key'));
    }

    public static function publishableKey(): ?string
    {
        return self::nullIfBlank(self::forMode('publishable_key'));
    }

    public static function webhookSecret(): ?string
    {
        return self::nullIfBlank(self::forMode('webhook_secret'));
    }

    /*
    |--------------------------------------------------------------------------
    | Gating
    |--------------------------------------------------------------------------
    */

    /**
     * Are the credentials needed to take a card payment present?
     *
     * Both keys are required. A publishable key with no secret key renders a
     * card form that cannot create an intent, which fails in front of a shopper
     * holding their wallet; refusing to offer the gateway at all is better.
     */
    public static function isConfigured(): bool
    {
        return self::secretKey() !== null && self::publishableKey() !== null;
    }

    /**
     * The single gate the card gateway hangs off.
     *
     * Both halves are re-checked on every request, not just at save time: if a
     * merchant later clears a key, the card option must disappear from checkout
     * rather than stay on the page and fail at the card field.
     */
    public static function isEnabled(): bool
    {
        return self::switchIsOn() && self::isConfigured();
    }

    /** Raw switch position, ignoring whether credentials back it up. */
    public static function switchIsOn(): bool
    {
        return Setting::get(self::KEY_ENABLED, '0') === '1';
    }

    /**
     * Webhooks are what make a payment durable when the shopper closes the tab
     * mid-redirect. Configured but with no webhook secret is a real state, and
     * the settings screen warns about it rather than pretending all is well.
     */
    public static function webhooksConfigured(): bool
    {
        return self::webhookSecret() !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    /** Shown on the shopper's card statement. Stripe allows 5 to 22 chars. */
    public static function statementDescriptor(): ?string
    {
        $value = self::nullIfBlank(Setting::get('stripe_statement_descriptor'));

        return $value ? substr($value, 0, 22) : null;
    }

    /** Where the merchant's own "you have an order" mail goes. */
    public static function merchantNotifyEmail(): ?string
    {
        return self::nullIfBlank(Setting::get('order_notify_email'))
            ?? self::nullIfBlank(Setting::get('store_email'));
    }

    public static function sendCustomerEmails(): bool
    {
        return Setting::get('order_email_customer', '1') === '1';
    }

    public static function sendMerchantEmails(): bool
    {
        return Setting::get('order_email_merchant', '1') === '1';
    }

    private static function filled($value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    private static function nullIfBlank($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
