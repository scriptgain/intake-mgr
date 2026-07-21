<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Payments\PaymentSettings;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Payment gateway configuration.
 *
 * Keys live in the settings table, never .env, per the fleet's DB-driven config
 * rule. Secrets are write-only in the UI: the form shows whether one is stored,
 * never the value, and only overwrites it when a replacement is actually typed,
 * so saving an unrelated field can never blank out a working key.
 *
 * TEST AND LIVE KEYS ARE STORED SEPARATELY and are edited on separate tabs.
 * Switching mode therefore neither destroys the other mode's credentials nor
 * risks a test key being used against real money.
 */
class PaymentSettingsController extends Controller
{
    /** The three per-mode credentials, and which of them are secret. */
    private const CREDENTIALS = ['publishable_key', 'secret_key', 'webhook_secret'];

    private const SECRETS = ['secret_key', 'webhook_secret'];

    public function edit()
    {
        $settings = Setting::map();

        return view('admin.settings.payments', [
            'settings' => $settings,
            'mode' => PaymentSettings::mode(),
            'isTestMode' => PaymentSettings::isTestMode(),

            // Whether a credential EXISTS, per mode. Never the value itself:
            // a secret that is rendered into a form field is a secret that is
            // in the page source, the browser cache, and any screenshot of it.
            'stored' => [
                'test' => [
                    'publishable_key' => PaymentSettings::hasCredential('test', 'publishable_key'),
                    'secret_key' => PaymentSettings::hasCredential('test', 'secret_key'),
                    'webhook_secret' => PaymentSettings::hasCredential('test', 'webhook_secret'),
                ],
                'live' => [
                    'publishable_key' => PaymentSettings::hasCredential('live', 'publishable_key'),
                    'secret_key' => PaymentSettings::hasCredential('live', 'secret_key'),
                    'webhook_secret' => PaymentSettings::hasCredential('live', 'webhook_secret'),
                ],
            ],

            // Publishable keys are public by definition, so the current one is
            // echoed back as a convenience. Secrets are not.
            'publishableKeys' => [
                'test' => Setting::get('stripe_test_publishable_key', Setting::get('stripe_publishable_key', '')),
                'live' => Setting::get('stripe_live_publishable_key', ''),
            ],

            // Everything the view needs to describe the current state, resolved
            // here rather than recomputed with conditionals in Blade.
            'isConfigured' => PaymentSettings::isConfigured(),
            'isEnabled' => PaymentSettings::isEnabled(),
            'switchIsOn' => PaymentSettings::switchIsOn(),
            'webhooksConfigured' => PaymentSettings::webhooksConfigured(),
            'state' => $this->state(),
            'webhookUrl' => route('stripe.webhook'),
            'gateways' => [
                'manual' => 'Manual / Offline Payment',
                'stripe' => 'Stripe',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'default_gateway' => ['required', Rule::in(['manual', 'stripe'])],
            'stripe_mode' => ['required', Rule::in(['test', 'live'])],
            'stripe_statement_descriptor' => ['nullable', 'string', 'max:22'],
            'manual_instructions' => ['nullable', 'string', 'max:2000'],
            'order_notify_email' => ['nullable', 'email', 'max:255'],

            'test_publishable_key' => ['nullable', 'string', 'max:255', 'starts_with:pk_test_'],
            'test_secret_key' => ['nullable', 'string', 'max:255', 'starts_with:sk_test_,rk_test_'],
            'test_webhook_secret' => ['nullable', 'string', 'max:255', 'starts_with:whsec_'],

            // starts_with is a real guard, not cosmetic: pasting a test key into
            // the live box is the single most common way a store ends up
            // silently taking no money at all.
            'live_publishable_key' => ['nullable', 'string', 'max:255', 'starts_with:pk_live_'],
            'live_secret_key' => ['nullable', 'string', 'max:255', 'starts_with:sk_live_,rk_live_'],
            'live_webhook_secret' => ['nullable', 'string', 'max:255', 'starts_with:whsec_'],
        ], [
            'test_publishable_key.starts_with' => 'A test publishable key starts with pk_test_.',
            'test_secret_key.starts_with' => 'A test secret key starts with sk_test_ or rk_test_.',
            'live_publishable_key.starts_with' => 'A live publishable key starts with pk_live_.',
            'live_secret_key.starts_with' => 'A live secret key starts with sk_live_ or rk_live_.',
            'test_webhook_secret.starts_with' => 'A webhook signing secret starts with whsec_.',
            'live_webhook_secret.starts_with' => 'A webhook signing secret starts with whsec_.',
        ]);

        foreach (['test', 'live'] as $mode) {
            foreach (self::CREDENTIALS as $credential) {
                $value = $request->input($mode.'_'.$credential);

                // A blank secret field means "leave it alone", not "clear it".
                // Clearing is a deliberate act with its own button.
                if (in_array($credential, self::SECRETS, true) && ! filled($value)) {
                    continue;
                }

                PaymentSettings::putForExplicitMode($mode, $credential, (string) $value);
            }
        }

        Setting::put('default_gateway', $data['default_gateway']);
        Setting::put('stripe_mode', $data['stripe_mode']);
        Setting::put('stripe_statement_descriptor', (string) ($data['stripe_statement_descriptor'] ?? ''));
        Setting::put('manual_instructions', (string) ($data['manual_instructions'] ?? ''));
        Setting::put('order_notify_email', (string) ($data['order_notify_email'] ?? ''));
        Setting::put('manual_enabled', $request->boolean('manual_enabled') ? '1' : '0');
        Setting::put('order_email_customer', $request->boolean('order_email_customer') ? '1' : '0');
        Setting::put('order_email_merchant', $request->boolean('order_email_merchant') ? '1' : '0');

        /*
         * The enable switch refuses to turn on without credentials behind it.
         * A half-enabled gateway offers a card option on checkout that cannot
         * create an intent, which fails in front of a paying customer.
         */
        $wantsStripe = $request->boolean('stripe_enabled');

        Setting::put('stripe_enabled', $wantsStripe && PaymentSettings::isConfigured() ? '1' : '0');

        // Mode changes are audited: test-to-live is the moment a store starts
        // taking real money, and that should be attributable.
        AuditLog::record('updated', 'Payment settings updated (mode: '.$data['stripe_mode'].')');

        if ($wantsStripe && ! PaymentSettings::isConfigured()) {
            return back()->with('warning', 'Saved, but card payments stay off until both a publishable key and a secret key are set for '.PaymentSettings::mode().' mode.');
        }

        return back()->with('status', 'Payment settings saved.');
    }

    /**
     * Prove the stored secret key actually works, before a customer is the one
     * who finds out it does not.
     */
    public function test()
    {
        if (! PaymentSettings::isConfigured()) {
            return back()->withErrors(['stripe' => 'Set a publishable key and a secret key first.']);
        }

        $result = StripeGateway::retrieveAccount();

        if (! $result['ok']) {
            // Staff see the real reason, redacted of anything key-shaped.
            return back()->withErrors([
                'stripe' => 'Stripe rejected that key: '.\App\Services\Payments\OrderPayments::redact($result['error']),
            ]);
        }

        $account = $result['data'];

        $name = $account['business_profile']['name']
            ?? $account['settings']['dashboard']['display_name']
            ?? $account['email']
            ?? $account['id']
            ?? 'your Stripe account';

        return back()->with('status', 'Connected to '.$name.' in '.PaymentSettings::mode().' mode.');
    }

    /** One of: disabled, not_configured, ready_test, ready_live. */
    private function state(): string
    {
        if (! PaymentSettings::isConfigured()) {
            return 'not_configured';
        }

        if (! PaymentSettings::switchIsOn()) {
            return 'disabled';
        }

        return PaymentSettings::isTestMode() ? 'ready_test' : 'ready_live';
    }
}
