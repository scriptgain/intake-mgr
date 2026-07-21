<?php

namespace App\Services\Captcha\Providers;

/**
 * Cloudflare Turnstile. A natural fit here: shop.allenjenkins.dev already sits
 * behind Cloudflare, so the challenge is served from the same edge. Mostly
 * invisible, no puzzle in the common case.
 */
class TurnstileProvider extends TokenVerifyProvider
{
    public function key(): string
    {
        return 'turnstile';
    }

    public function label(): string
    {
        return 'Cloudflare Turnstile';
    }

    public function description(): string
    {
        return 'Cloudflare\'s privacy-preserving challenge. A natural fit as the site is already behind Cloudflare.';
    }

    protected function verifyUrl(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }

    protected function siteKeyName(): string
    {
        return 'captcha_turnstile_site_key';
    }

    protected function secretKeyName(): string
    {
        return 'captcha_turnstile_secret_key';
    }

    public function responseField(): string
    {
        return 'cf-turnstile-response';
    }

    protected function containerClass(): string
    {
        return 'cf-turnstile';
    }

    protected function scriptUrl(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    }
}
