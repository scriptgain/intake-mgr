<?php

namespace App\Services\Captcha;

use App\Models\AuditLog;
use App\Services\Captcha\Providers\BuiltinProvider;
use App\Services\Captcha\Providers\HcaptchaProvider;
use App\Services\Captcha\Providers\NullProvider;
use App\Services\Captcha\Providers\RecaptchaV2Provider;
use App\Services\Captcha\Providers\RecaptchaV3Provider;
use App\Services\Captcha\Providers\TurnstileProvider;
use Illuminate\Http\Request;

/**
 * The spam-protection registry and orchestrator.
 *
 * ADD A PROVIDER: implement CaptchaProvider, then add one line to PROVIDERS
 * below. Third-party widgets need nothing else; the shared <x-captcha> component
 * renders them from widgetConfig(). That is the whole extension surface.
 *
 * ORDER OF PROTECTION on a protected surface:
 *   1. Honeypot + time-trap baseline (always, unless globally disabled). A trip
 *      here blocks immediately: it is a local, definite bot signal.
 *   2. The active provider, but only if this surface's toggle is on. Its result
 *      is subject to the fail policy: a definite rejection blocks; an outage
 *      fails closed on a login and (by default) open on a contact form.
 */
class CaptchaManager
{
    /** key => provider class. The one place a new provider is wired in. */
    public const PROVIDERS = [
        'none' => NullProvider::class,
        'builtin' => BuiltinProvider::class,
        'recaptcha_v2' => RecaptchaV2Provider::class,
        'recaptcha_v3' => RecaptchaV3Provider::class,
        'hcaptcha' => HcaptchaProvider::class,
        'turnstile' => TurnstileProvider::class,
    ];

    private Honeypot $honeypot;

    public function __construct(?Honeypot $honeypot = null)
    {
        $this->honeypot = $honeypot ?? new Honeypot;
    }

    /*
    |--------------------------------------------------------------------------
    | Registry
    |--------------------------------------------------------------------------
    */

    /** @return CaptchaProvider[] keyed by provider key, for the admin picker. */
    public function providers(): array
    {
        $out = [];

        foreach (self::PROVIDERS as $key => $class) {
            $out[$key] = new $class;
        }

        return $out;
    }

    public function provider(string $key): CaptchaProvider
    {
        $class = self::PROVIDERS[$key] ?? NullProvider::class;

        return new $class;
    }

    /**
     * The configured active provider. Falls back to the honeypot-only "none"
     * provider if the configured one is unknown or a third-party provider is
     * selected with no keys, so a form can never render an unusable widget.
     */
    public function active(): CaptchaProvider
    {
        $provider = $this->provider(CaptchaSettings::providerKey());

        if ($provider->isThirdParty() && ! $provider->isConfigured()) {
            return new NullProvider;
        }

        return $provider;
    }

    public function honeypot(): Honeypot
    {
        return $this->honeypot;
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering support
    |--------------------------------------------------------------------------
    */

    /** Should the active PROVIDER widget appear / be required on this surface? */
    public function providerActiveOn(string $surface): bool
    {
        return $this->active()->key() !== 'none'
            && CaptchaSettings::surfaceEnabled($surface);
    }

    /** Is this surface protected at all (provider widget OR baseline)? */
    public function protects(string $surface): bool
    {
        return $this->providerActiveOn($surface) || CaptchaSettings::honeypotEnabled();
    }

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    */

    /**
     * The single entry point callers use. Never throws.
     */
    public function verify(Request $request, string $surface): CaptchaOutcome
    {
        // 1) Baseline. Always on (unless globally disabled), independent of the
        //    provider and of the per-surface provider toggle.
        if (CaptchaSettings::honeypotEnabled()) {
            $hp = $this->honeypot->check($request);

            if (! $hp['ok']) {
                $this->audit($surface, 'blocked (baseline: '.$hp['reason'].')');

                return CaptchaOutcome::block(
                    $this->genericMessage(),
                    'baseline_'.$hp['reason'],
                );
            }
        }

        // 2) Provider, only where its surface toggle is on.
        if (! $this->providerActiveOn($surface)) {
            return CaptchaOutcome::pass();
        }

        $provider = $this->active();
        $result = $provider->verify($request);

        if ($result->ok) {
            return CaptchaOutcome::pass();
        }

        // A definite rejection (bad/missing/expired token, low score) always
        // blocks: it is not an outage, so the fail policy does not apply.
        if ($result->reachable) {
            $this->audit($surface, 'blocked ('.$provider->key().': '.$result->reason.')');

            return CaptchaOutcome::block($this->genericMessage(), $provider->key().'_'.$result->reason);
        }

        // Unreachable: the fail policy decides.
        $policy = CaptchaSettings::failPolicyFor($surface);

        if ($policy === 'open') {
            $reason = 'captcha_unreachable_failed_open:'.$provider->key().':'.$result->reason;
            $this->audit($surface, 'allowed despite '.$provider->key().' being unreachable ('.$result->reason.'); failed open');

            return CaptchaOutcome::passFailOpen($reason);
        }

        $this->audit($surface, 'blocked ('.$provider->key().' unreachable: '.$result->reason.'); failed closed');

        return CaptchaOutcome::block(
            'We could not verify the anti-spam check right now. Please try again in a moment.',
            'captcha_unreachable_failed_closed:'.$provider->key().':'.$result->reason,
        );
    }

    /** Deliberately vague so it can't be used to tune a bot against a signal. */
    private function genericMessage(): string
    {
        return 'That submission did not pass our spam check. Please try again.';
    }

    private function audit(string $surface, string $detail): void
    {
        try {
            AuditLog::record('captcha', 'Spam check on '.$surface.': '.$detail);
        } catch (\Throwable $e) {
            // Auditing must never turn a spam check into a 500.
        }
    }
}
