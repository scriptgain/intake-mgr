<?php

namespace App\Services\Captcha;

use Illuminate\Http\Request;

/**
 * One pluggable anti-bot provider.
 *
 * Adding a provider is: write a class implementing this interface, then register
 * it in CaptchaManager::PROVIDERS. Third-party widgets (reCAPTCHA, hCaptcha,
 * Turnstile) all render as a container div plus a script, so the shared
 * <x-captcha> component draws them from widgetConfig() with no per-provider
 * Blade. A provider whose widget is genuinely different (the built-in challenge)
 * ships its own partial and points widgetView() at it.
 *
 * VERIFICATION IS ALWAYS SERVER-SIDE. verify() is handed the raw request and
 * returns a CaptchaResult; a provider never trusts a value the browser reports
 * as "already checked".
 */
interface CaptchaProvider
{
    /** Stable registry key, e.g. 'recaptcha_v2'. Stored in settings. */
    public function key(): string;

    /** Human label for the admin picker, Title Case. */
    public function label(): string;

    /** One line describing the provider for the settings screen. */
    public function description(): string;

    /** Does this provider talk to a third party (needs keys + a widget script)? */
    public function isThirdParty(): bool;

    /**
     * Are the credentials this provider needs present? A third-party provider
     * with no site/secret key must not be selectable as active, or a form would
     * render a widget that can never verify.
     */
    public function isConfigured(): bool;

    /**
     * Server-side verification of the current request.
     *
     * Must never throw: a provider outage returns CaptchaResult::unreachable so
     * the caller can apply the fail policy rather than 500 the form.
     */
    public function verify(Request $request): CaptchaResult;

    /**
     * Widget rendering hints for the shared component. Keys used:
     *   container_class : the div class the vendor script hydrates
     *   script_url      : the vendor widget script (loaded ONLY when active)
     *   response_field  : the request field the token arrives in
     *   needs_execute   : true for invisible/score widgets that must be run by
     *                     captcha.js on submit (reCAPTCHA v3)
     *   theme_attr      : optional extra data-* attributes as an assoc array
     *
     * Returns [] for providers with no browser widget (none, honeypot-only).
     */
    public function widgetConfig(): array;

    /**
     * A dedicated Blade partial for providers whose widget cannot be expressed
     * as a container + script (the built-in challenge). null means "use the
     * generic third-party renderer in <x-captcha>".
     */
    public function widgetView(): ?string;

    /** The public site key, echoed into the widget. null when not applicable. */
    public function siteKey(): ?string;
}
