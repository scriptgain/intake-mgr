<?php

namespace App\Services\Captcha\Providers;

/**
 * hCaptcha checkbox. A privacy-focused drop-in for reCAPTCHA v2 with the same
 * checkbox interaction and the same verify shape.
 */
class HcaptchaProvider extends TokenVerifyProvider
{
    public function key(): string
    {
        return 'hcaptcha';
    }

    public function label(): string
    {
        return 'hCaptcha';
    }

    public function description(): string
    {
        return 'A privacy-first checkbox widget, drop-in comparable to reCAPTCHA v2.';
    }

    protected function verifyUrl(): string
    {
        return 'https://api.hcaptcha.com/siteverify';
    }

    protected function siteKeyName(): string
    {
        return 'captcha_hcaptcha_site_key';
    }

    protected function secretKeyName(): string
    {
        return 'captcha_hcaptcha_secret_key';
    }

    public function responseField(): string
    {
        return 'h-captcha-response';
    }

    protected function containerClass(): string
    {
        return 'h-captcha';
    }

    protected function scriptUrl(): string
    {
        return 'https://js.hcaptcha.com/1/api.js';
    }
}
