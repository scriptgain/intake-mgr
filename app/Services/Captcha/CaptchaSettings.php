<?php

namespace App\Services\Captcha;

use App\Models\Setting;

/**
 * Typed accessor over the DB Setting store for spam protection.
 *
 * Every value lives in the settings table, never .env, per the fleet's
 * DB-driven config rule. Views never touch this: controllers and the
 * <x-captcha> component read it and hand plain values to the template.
 *
 * Secret keys are write-only in the UI. hasSecret() answers "is one stored"
 * for the form; the value itself is never echoed back into a field.
 */
class CaptchaSettings
{
    /** The active provider key. */
    public const KEY_PROVIDER = 'captcha_provider';

    /** Surfaces that can be individually toggled, with their default state. */
    public const SURFACES = [
        'admin_login' => true,
        'account_login' => true,
        'account_register' => true,
        'account_forgot' => true,
        'contact' => true,
        'checkout' => false,   // OFF by default: a captcha on checkout costs orders.
    ];

    /*
    |--------------------------------------------------------------------------
    | Active provider
    |--------------------------------------------------------------------------
    */

    public static function providerKey(): string
    {
        return (string) Setting::get(self::KEY_PROVIDER, 'builtin');
    }

    /*
    |--------------------------------------------------------------------------
    | Per-surface toggles
    |--------------------------------------------------------------------------
    */

    /** Is the PROVIDER captcha required on this surface? */
    public static function surfaceEnabled(string $surface): bool
    {
        $default = self::SURFACES[$surface] ?? false;

        return Setting::get('captcha_on_'.$surface, $default ? '1' : '0') === '1';
    }

    /*
    |--------------------------------------------------------------------------
    | Honeypot + time-trap baseline
    |--------------------------------------------------------------------------
    */

    /** Baseline honeypot + time-trap. On by default; independent of provider. */
    public static function honeypotEnabled(): bool
    {
        return Setting::get('captcha_honeypot_enabled', '1') === '1';
    }

    /** Minimum seconds a real human takes to submit. Faster than this = a bot. */
    public static function minSeconds(): int
    {
        return max(0, (int) Setting::get('captcha_min_seconds', 2));
    }

    /*
    |--------------------------------------------------------------------------
    | Fail policy
    |--------------------------------------------------------------------------
    */

    /**
     * What to do when the provider is UNREACHABLE (not when it says no).
     *
     * Global default is 'closed': an unreachable captcha on a login should stop
     * the login, not wave it through. The contact surface is the documented
     * exception and defaults to failing open so an outage never eats a genuine
     * enquiry; that is itself overridable.
     *
     * @return 'closed'|'open'
     */
    public static function failPolicyFor(string $surface): string
    {
        if ($surface === 'contact') {
            return Setting::get('captcha_contact_fail_open', '1') === '1' ? 'open' : 'closed';
        }

        return Setting::get('captcha_fail_policy', 'closed') === 'open' ? 'open' : 'closed';
    }

    /*
    |--------------------------------------------------------------------------
    | Provider credentials (write-only secrets)
    |--------------------------------------------------------------------------
    */

    public static function get(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }

    public static function nullIfBlank($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    /** Is a value stored for this settings key (used for "Configured" badges)? */
    public static function has(string $key): bool
    {
        return self::nullIfBlank(Setting::get($key)) !== null;
    }

    /** reCAPTCHA v3 pass threshold. Google returns 0.0 (bot) .. 1.0 (human). */
    public static function v3Threshold(): float
    {
        $v = (float) Setting::get('captcha_recaptcha_v3_threshold', 0.5);

        return ($v < 0 || $v > 1) ? 0.5 : $v;
    }
}
