<?php

namespace App\Services\Captcha\Providers;

/**
 * Google reCAPTCHA v2 ("I'm not a robot" checkbox).
 *
 * The shopper ticks a box; Google returns a token in g-recaptcha-response, which
 * we verify server-side against the siteverify endpoint.
 */
class RecaptchaV2Provider extends TokenVerifyProvider
{
    public function key(): string
    {
        return 'recaptcha_v2';
    }

    public function label(): string
    {
        return 'Google reCAPTCHA v2';
    }

    public function description(): string
    {
        return 'The familiar "I\'m not a robot" checkbox. A visible widget the shopper ticks.';
    }

    protected function verifyUrl(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }

    protected function siteKeyName(): string
    {
        return 'captcha_recaptcha_v2_site_key';
    }

    protected function secretKeyName(): string
    {
        return 'captcha_recaptcha_v2_secret_key';
    }

    public function responseField(): string
    {
        return 'g-recaptcha-response';
    }

    protected function containerClass(): string
    {
        return 'g-recaptcha';
    }

    protected function scriptUrl(): string
    {
        return 'https://www.google.com/recaptcha/api.js';
    }
}
