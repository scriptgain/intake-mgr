<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Captcha\CaptchaManager;
use App\Services\Captcha\CaptchaSettings;
use App\Services\Captcha\Providers\BuiltinProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

/**
 * Spam Protection settings.
 *
 * All configuration lives in the settings table, never .env, per the fleet's
 * DB-driven config rule. Secret keys are write-only in the UI: the form reports
 * whether one is stored, never its value, and only overwrites it when a
 * replacement is actually typed, so saving an unrelated field can never blank a
 * working key. Site keys are public by definition and are echoed back.
 */
class SpamProtectionController extends Controller
{
    /**
     * Per-provider credential setting keys. Adding a third-party provider means
     * adding its class to CaptchaManager and one row here; the view iterates it.
     */
    private const PROVIDER_CREDENTIALS = [
        'recaptcha_v2' => [
            'site' => 'captcha_recaptcha_v2_site_key',
            'secret' => 'captcha_recaptcha_v2_secret_key',
        ],
        'recaptcha_v3' => [
            'site' => 'captcha_recaptcha_v3_site_key',
            'secret' => 'captcha_recaptcha_v3_secret_key',
        ],
        'hcaptcha' => [
            'site' => 'captcha_hcaptcha_site_key',
            'secret' => 'captcha_hcaptcha_secret_key',
        ],
        'turnstile' => [
            'site' => 'captcha_turnstile_site_key',
            'secret' => 'captcha_turnstile_secret_key',
        ],
    ];

    /** Dummy tokens for the connectivity test. The official TEST secret keys
     *  accept these and return success; real secrets return a benign
     *  invalid-input-response, which still proves the endpoint is reachable. */
    private const TEST_TOKENS = [
        'recaptcha_v2' => 'test-token',
        'recaptcha_v3' => 'test-token',
        'hcaptcha' => '10000000-aaaa-bbbb-cccc-000000000001',
        'turnstile' => 'XXXX.DUMMY.TOKEN.XXXX',
    ];

    public function edit(CaptchaManager $manager)
    {
        $settings = Setting::map();

        $providers = [];
        foreach ($manager->providers() as $key => $provider) {
            $providers[$key] = [
                'label' => $provider->label(),
                'description' => $provider->description(),
                'third_party' => $provider->isThirdParty(),
                'configured' => $provider->isConfigured(),
            ];
        }

        // Whether a SECRET is stored, per provider (never the value itself), and
        // the current PUBLIC site key (safe to show).
        $stored = [];
        $siteKeys = [];
        foreach (self::PROVIDER_CREDENTIALS as $pkey => $names) {
            $stored[$pkey] = CaptchaSettings::has($names['secret']);
            $siteKeys[$pkey] = (string) Setting::get($names['site'], '');
        }

        return view('admin.settings.spam', [
            'settings' => $settings,
            'providers' => $providers,
            'activeProvider' => CaptchaSettings::providerKey(),
            'stored' => $stored,
            'siteKeys' => $siteKeys,
            'credentialKeys' => self::PROVIDER_CREDENTIALS,
            'surfaces' => $this->surfaceMeta(),
            'honeypotEnabled' => CaptchaSettings::honeypotEnabled(),
            'minSeconds' => CaptchaSettings::minSeconds(),
            'failPolicy' => Setting::get('captcha_fail_policy', 'closed'),
            'contactFailOpen' => Setting::get('captcha_contact_fail_open', '1') === '1',
            'v3Threshold' => CaptchaSettings::v3Threshold(),
        ]);
    }

    public function update(Request $request, CaptchaManager $manager)
    {
        $providerKeys = array_keys(CaptchaManager::PROVIDERS);

        $rules = [
            'captcha_provider' => ['required', Rule::in($providerKeys)],
            'captcha_min_seconds' => ['required', 'integer', 'min:0', 'max:120'],
            'captcha_fail_policy' => ['required', Rule::in(['closed', 'open'])],
            'captcha_recaptcha_v3_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
        ];

        foreach (self::PROVIDER_CREDENTIALS as $names) {
            $rules[$names['site']] = ['nullable', 'string', 'max:255'];
            $rules[$names['secret']] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        // A third-party provider cannot be made active without its keys, or every
        // form would render a widget that can never verify. Fall back to keeping
        // the previous provider and warn.
        $chosen = $data['captcha_provider'];
        $provider = $manager->provider($chosen);
        $blockedActivation = false;

        // Persist credentials first so isConfigured() reflects this very save.
        foreach (self::PROVIDER_CREDENTIALS as $names) {
            // Site keys are public: save as given, blank clears.
            Setting::put($names['site'], (string) ($data[$names['site']] ?? ''));

            // Secrets are write-only: a blank field means "keep the stored one".
            $secret = $data[$names['secret']] ?? null;
            if (filled($secret)) {
                Setting::put($names['secret'], (string) $secret);
            }
        }

        if ($provider->isThirdParty() && ! $manager->provider($chosen)->isConfigured()) {
            $chosen = CaptchaSettings::providerKey(); // keep the current one
            $blockedActivation = true;
        }

        Setting::put('captcha_provider', $chosen);

        // Baseline + policy.
        Setting::put('captcha_honeypot_enabled', $request->boolean('captcha_honeypot_enabled') ? '1' : '0');
        Setting::put('captcha_min_seconds', (string) $data['captcha_min_seconds']);
        Setting::put('captcha_fail_policy', $data['captcha_fail_policy']);
        Setting::put('captcha_contact_fail_open', $request->boolean('captcha_contact_fail_open') ? '1' : '0');
        Setting::put('captcha_recaptcha_v3_threshold', (string) $data['captcha_recaptcha_v3_threshold']);

        // Per-surface toggles.
        foreach (array_keys(CaptchaSettings::SURFACES) as $surface) {
            Setting::put('captcha_on_'.$surface, $request->boolean('captcha_on_'.$surface) ? '1' : '0');
        }

        AuditLog::record('updated', 'Spam protection settings updated (provider: '.$chosen.')');

        if ($blockedActivation) {
            return back()->with('warning', 'Saved, but that provider needs both a site key and a secret key before it can be made active. The previous provider is still in use.');
        }

        return back()->with('status', 'Spam protection settings saved.');
    }

    /**
     * Test the active configuration with a real round trip where one is possible.
     */
    public function test(CaptchaManager $manager)
    {
        $provider = $manager->active();
        $key = $provider->key();

        if ($key === 'none') {
            return back()->with('status', 'No interactive provider is active. The honeypot and time-trap baseline are always on and need no external check.');
        }

        if ($provider instanceof BuiltinProvider) {
            $result = $provider->selfTest();

            return $result['ok']
                ? back()->with('status', 'Built-in challenge: '.$result['detail'])
                : back()->withErrors(['captcha' => 'Built-in challenge: '.$result['detail']]);
        }

        // Third-party: hit siteverify with a dummy token. Test keys accept it;
        // real keys reject the token but still answer, proving reachability.
        $names = self::PROVIDER_CREDENTIALS[$key] ?? null;
        $secret = $names ? CaptchaSettings::nullIfBlank(Setting::get($names['secret'])) : null;

        if ($secret === null) {
            return back()->withErrors(['captcha' => 'Add a secret key for '.$provider->label().' first.']);
        }

        $endpoints = [
            'recaptcha_v2' => 'https://www.google.com/recaptcha/api/siteverify',
            'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
            'hcaptcha' => 'https://api.hcaptcha.com/siteverify',
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ];

        try {
            $response = Http::asForm()->timeout(8)->post($endpoints[$key], [
                'secret' => $secret,
                'response' => self::TEST_TOKENS[$key] ?? 'test-token',
            ]);

            if (! $response->successful()) {
                return back()->withErrors(['captcha' => 'Reached '.$provider->label().' but it returned HTTP '.$response->status().'.']);
            }

            $body = $response->json() ?? [];

            if (($body['success'] ?? false) === true) {
                return back()->with('status', $provider->label().': live round-trip succeeded (test token accepted).');
            }

            $codes = $body['error-codes'] ?? [];
            $codes = is_array($codes) ? implode(', ', $codes) : (string) $codes;

            return back()->with('warning', 'Connected to '.$provider->label().'. It rejected the dummy test token ('.($codes ?: 'no code').'), which is expected with real keys and confirms your secret and connectivity. A genuine token from the widget will pass at checkout.');
        } catch (\Throwable $e) {
            return back()->withErrors(['captcha' => 'Could not reach '.$provider->label().': '.class_basename($e).'.']);
        }
    }

    /** Surface labels + descriptions for the toggles, plus their defaults. */
    private function surfaceMeta(): array
    {
        return [
            'admin_login' => ['Admin Login', 'The staff sign-in page. A brute-force surface.', CaptchaSettings::surfaceEnabled('admin_login')],
            'account_login' => ['Customer Login', 'Storefront account sign-in. A credential-stuffing surface.', CaptchaSettings::surfaceEnabled('account_login')],
            'account_register' => ['Customer Registration', 'New storefront accounts. A fake-account surface.', CaptchaSettings::surfaceEnabled('account_register')],
            'account_forgot' => ['Customer Password Reset', 'Forgot / reset password forms. An email-enumeration and mail-flood surface.', CaptchaSettings::surfaceEnabled('account_forgot')],
            'contact' => ['Contact / Newsletter', 'Any storefront contact or newsletter form. Fails open by default.', CaptchaSettings::surfaceEnabled('contact')],
            'checkout' => ['Guest Checkout', 'Sensitive: a challenge here can cost real orders. Off by default.', CaptchaSettings::surfaceEnabled('checkout')],
        ];
    }
}
