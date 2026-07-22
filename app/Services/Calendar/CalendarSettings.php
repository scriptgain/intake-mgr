<?php

namespace App\Services\Calendar;

use App\Models\Setting;

/**
 * Typed accessor over the DB Setting store for the four calendar providers
 * (Google, Microsoft, Apple, Nylas). App-level OAuth client credentials live in
 * the settings table, never .env, per the fleet's DB-driven config rule. Views
 * never call this: controllers and composers read it and hand plain values to
 * the template. Mirrors AuthorizeNetSettings in shape and gating.
 *
 * PER-STAFF vs APP-LEVEL. Google, Microsoft and Nylas need one app-level OAuth
 * client (client id + secret) that every staff member authorizes against. Apple
 * has NO app-level credentials at all: each staff member enters their own Apple
 * ID + app-specific password on the My Calendar page, so the only app-level
 * setting Apple has is the on/off switch.
 *
 * A provider is CONNECTABLE when its switch is on AND it is configured
 * (isConfigured), re-checked on every request, so clearing a client secret
 * makes the "Connect" button disappear rather than fail mid-OAuth.
 */
class CalendarSettings
{
    /* ---- Google ------------------------------------------------------- */
    public const KEY_GOOGLE_ENABLED = 'google_calendar_enabled';

    public const KEY_GOOGLE_CLIENT_ID = 'google_client_id';

    public const KEY_GOOGLE_CLIENT_SECRET = 'google_client_secret';

    /* ---- Microsoft ---------------------------------------------------- */
    public const KEY_MICROSOFT_ENABLED = 'microsoft_calendar_enabled';

    public const KEY_MICROSOFT_CLIENT_ID = 'microsoft_client_id';

    public const KEY_MICROSOFT_CLIENT_SECRET = 'microsoft_client_secret';

    public const KEY_MICROSOFT_TENANT = 'microsoft_tenant';

    /* ---- Apple -------------------------------------------------------- */
    public const KEY_APPLE_ENABLED = 'apple_calendar_enabled';

    /* ---- Nylas -------------------------------------------------------- */
    public const KEY_NYLAS_ENABLED = 'nylas_enabled';

    public const KEY_NYLAS_API_KEY = 'nylas_api_key';

    public const KEY_NYLAS_CLIENT_ID = 'nylas_client_id';

    public const KEY_NYLAS_API_REGION = 'nylas_api_region';

    /** Settings that are secrets: never echoed back into a form field. */
    public const SECRET_KEYS = [
        'google_client_secret',
        'microsoft_client_secret',
        'nylas_api_key',
    ];

    /** Every provider key this reader understands. */
    public const PROVIDERS = ['google', 'microsoft', 'apple', 'nylas'];

    /*
    |--------------------------------------------------------------------------
    | Google
    |--------------------------------------------------------------------------
    */

    public static function googleClientId(): ?string
    {
        return self::nullIfBlank(Setting::get(self::KEY_GOOGLE_CLIENT_ID));
    }

    public static function googleClientSecret(): ?string
    {
        return self::nullIfBlank(Setting::get(self::KEY_GOOGLE_CLIENT_SECRET));
    }

    /*
    |--------------------------------------------------------------------------
    | Microsoft
    |--------------------------------------------------------------------------
    */

    public static function microsoftClientId(): ?string
    {
        return self::nullIfBlank(Setting::get(self::KEY_MICROSOFT_CLIENT_ID));
    }

    public static function microsoftClientSecret(): ?string
    {
        return self::nullIfBlank(Setting::get(self::KEY_MICROSOFT_CLIENT_SECRET));
    }

    /** The Azure AD tenant to auth against. 'common' works for personal + work accounts. */
    public static function microsoftTenant(): string
    {
        return self::nullIfBlank(Setting::get(self::KEY_MICROSOFT_TENANT)) ?? 'common';
    }

    /*
    |--------------------------------------------------------------------------
    | Nylas
    |--------------------------------------------------------------------------
    */

    public static function nylasApiKey(): ?string
    {
        return self::nullIfBlank(Setting::get(self::KEY_NYLAS_API_KEY));
    }

    public static function nylasClientId(): ?string
    {
        return self::nullIfBlank(Setting::get(self::KEY_NYLAS_CLIENT_ID));
    }

    /** 'us' or 'eu'. Selects which Nylas data region the API base points at. */
    public static function nylasApiRegion(): string
    {
        return Setting::get(self::KEY_NYLAS_API_REGION, 'us') === 'eu' ? 'eu' : 'us';
    }

    /** The Nylas v3 API base for the configured region. */
    public static function nylasApiBase(): string
    {
        return self::nylasApiRegion() === 'eu'
            ? 'https://api.eu.nylas.com'
            : 'https://api.us.nylas.com';
    }

    /*
    |--------------------------------------------------------------------------
    | Gating
    |--------------------------------------------------------------------------
    */

    /**
     * Are the app-level credentials this provider needs present?
     *
     * Google / Microsoft need a client id + secret. Nylas needs an API key.
     * Apple has no app-level credentials, so it is "configured" the moment its
     * switch could be turned on: each staff member supplies their own Apple ID
     * and app-specific password when they connect.
     */
    public static function isConfigured(string $provider): bool
    {
        return match ($provider) {
            'google' => self::googleClientId() !== null && self::googleClientSecret() !== null,
            'microsoft' => self::microsoftClientId() !== null && self::microsoftClientSecret() !== null,
            'apple' => true,
            'nylas' => self::nylasApiKey() !== null && self::nylasClientId() !== null,
            default => false,
        };
    }

    /**
     * The single gate a provider's "Connect" button hangs off. Both halves are
     * re-checked on every request: if an admin later clears a credential the
     * connect option must disappear rather than stay and fail mid-OAuth.
     */
    public static function enabled(string $provider): bool
    {
        return self::switchIsOn($provider) && self::isConfigured($provider);
    }

    /** Raw switch position, ignoring whether credentials back it up. */
    public static function switchIsOn(string $provider): bool
    {
        $key = match ($provider) {
            'google' => self::KEY_GOOGLE_ENABLED,
            'microsoft' => self::KEY_MICROSOFT_ENABLED,
            'apple' => self::KEY_APPLE_ENABLED,
            'nylas' => self::KEY_NYLAS_ENABLED,
            default => null,
        };

        return $key !== null && Setting::get($key, '0') === '1';
    }

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    */

    /**
     * The OAuth redirect/callback URI for a provider, which must be registered
     * verbatim in the provider's developer console. The route is wired by the
     * coordinator; this only builds the absolute URL for it.
     */
    public static function redirectUri(string $provider): string
    {
        return route('calendar.callback', ['provider' => $provider]);
    }

    private static function nullIfBlank($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
