<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Calendar\CalendarManager;
use App\Services\Calendar\CalendarSettings;
use App\Services\Payments\OrderPayments;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Calendar provider configuration (Google, Microsoft, Apple, Nylas).
 *
 * App-level OAuth client credentials live in the settings table, never .env, per
 * the fleet's DB-driven config rule. Secrets are write-only in the UI: the form
 * shows whether one is stored, never the value, and only overwrites it when a
 * replacement is actually typed, so saving an unrelated field can never blank out
 * a working secret. Mirrors PaymentSettingsController.
 *
 * A provider's enable switch is CLAMPED to its configuration: turning it on
 * without the credentials behind it stores off, so a staff member is never
 * offered a "Connect" button that fails mid-OAuth.
 */
class CalendarSettingsController extends Controller
{
    public function edit()
    {
        $providers = CalendarSettings::PROVIDERS;

        $configured = [];
        $enabled = [];
        foreach ($providers as $provider) {
            $configured[$provider] = CalendarSettings::isConfigured($provider);
            $enabled[$provider] = CalendarSettings::enabled($provider);
        }

        return view('admin.settings.calendar', [
            'settings' => Setting::map(),
            'configured' => $configured,
            'enabled' => $enabled,

            // Whether a secret EXISTS. Never the value: a secret rendered into a
            // form field is a secret in the page source and any screenshot of it.
            'has_google_secret' => filled(Setting::get(CalendarSettings::KEY_GOOGLE_CLIENT_SECRET)),
            'has_microsoft_secret' => filled(Setting::get(CalendarSettings::KEY_MICROSOFT_CLIENT_SECRET)),
            'has_nylas_key' => filled(Setting::get(CalendarSettings::KEY_NYLAS_API_KEY)),

            // Each provider's redirect/callback URI, to paste into its console.
            'redirectUris' => [
                'google' => CalendarSettings::redirectUri('google'),
                'microsoft' => CalendarSettings::redirectUri('microsoft'),
                'nylas' => CalendarSettings::redirectUri('nylas'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],

            'microsoft_client_id' => ['nullable', 'string', 'max:255'],
            'microsoft_client_secret' => ['nullable', 'string', 'max:255'],
            'microsoft_tenant' => ['nullable', 'string', 'max:255'],

            'nylas_client_id' => ['nullable', 'string', 'max:255'],
            'nylas_api_key' => ['nullable', 'string', 'max:255'],
            'nylas_api_region' => ['required', Rule::in(['us', 'eu'])],
        ]);

        // Public fields: always saved (blank clears them, which is intended for a
        // non-secret identifier).
        Setting::put(CalendarSettings::KEY_GOOGLE_CLIENT_ID, (string) ($data['google_client_id'] ?? ''));
        Setting::put(CalendarSettings::KEY_MICROSOFT_CLIENT_ID, (string) ($data['microsoft_client_id'] ?? ''));
        Setting::put(CalendarSettings::KEY_MICROSOFT_TENANT, trim((string) ($data['microsoft_tenant'] ?? '')) ?: 'common');
        Setting::put(CalendarSettings::KEY_NYLAS_CLIENT_ID, (string) ($data['nylas_client_id'] ?? ''));
        Setting::put(CalendarSettings::KEY_NYLAS_API_REGION, $data['nylas_api_region']);

        // Secrets: only written when a replacement is actually typed. A blank
        // field means "leave it alone", not "clear it".
        foreach (CalendarSettings::SECRET_KEYS as $secret) {
            if (filled($request->input($secret))) {
                Setting::put($secret, (string) $request->input($secret));
            }
        }

        // Enable switches, each clamped to that provider being configured. Apple
        // has no app-level credentials, so its switch is never clamped off.
        foreach (['google' => CalendarSettings::KEY_GOOGLE_ENABLED,
            'microsoft' => CalendarSettings::KEY_MICROSOFT_ENABLED,
            'apple' => CalendarSettings::KEY_APPLE_ENABLED,
            'nylas' => CalendarSettings::KEY_NYLAS_ENABLED] as $provider => $key) {
            $wants = $request->boolean($key);
            Setting::put($key, $wants && CalendarSettings::isConfigured($provider) ? '1' : '0');
        }

        AuditLog::record('updated', 'Calendar settings updated');

        // Warn if an admin tried to enable a provider that is still missing creds.
        foreach (['google', 'microsoft', 'nylas'] as $provider) {
            $key = [
                'google' => CalendarSettings::KEY_GOOGLE_ENABLED,
                'microsoft' => CalendarSettings::KEY_MICROSOFT_ENABLED,
                'nylas' => CalendarSettings::KEY_NYLAS_ENABLED,
            ][$provider];

            if ($request->boolean($key) && ! CalendarSettings::isConfigured($provider)) {
                return back()->with('warning', 'Saved, but '.ucfirst($provider).' stays off until its credentials are complete.');
            }
        }

        return back()->with('status', 'Calendar settings saved.');
    }

    /**
     * Prove a provider's stored app-level credentials actually answer, before a
     * staff member is the one who finds out they do not.
     */
    public function test(string $provider)
    {
        $impl = app(CalendarManager::class)->get($provider);

        if (! $impl) {
            return back()->withErrors(['calendar' => 'Unknown calendar provider.']);
        }

        $result = $impl->testConnection();

        if (! ($result['ok'] ?? false)) {
            return back()->withErrors([
                'calendar' => $impl->label().': '.OrderPayments::redact($result['message'] ?? 'The credential test failed.'),
            ]);
        }

        return back()->with('status', $impl->label().': '.($result['message'] ?? 'Connected.'));
    }
}
