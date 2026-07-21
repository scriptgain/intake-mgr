<?php

namespace App\Services\Captcha\Providers;

use App\Services\Captcha\CaptchaProvider;
use App\Services\Captcha\CaptchaResult;
use Illuminate\Http\Request;

/**
 * "None": no interactive challenge at all.
 *
 * This is NOT "no protection". The honeypot and time-trap baseline in
 * CaptchaManager still run on every protected surface, so a form is never left
 * fully open. This provider simply adds no widget and always passes its own
 * verification step.
 */
class NullProvider implements CaptchaProvider
{
    public function key(): string
    {
        return 'none';
    }

    public function label(): string
    {
        return 'None (Honeypot Only)';
    }

    public function description(): string
    {
        return 'No interactive challenge. The honeypot and time-trap still protect every form.';
    }

    public function isThirdParty(): bool
    {
        return false;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function siteKey(): ?string
    {
        return null;
    }

    public function widgetConfig(): array
    {
        return [];
    }

    public function widgetView(): ?string
    {
        return null;
    }

    public function verify(Request $request): CaptchaResult
    {
        return CaptchaResult::pass();
    }
}
