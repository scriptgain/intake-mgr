<?php

namespace App\Services\Captcha\Providers;

use App\Services\Captcha\CaptchaResult;
use App\Services\Captcha\CaptchaSettings;

/**
 * Google reCAPTCHA v3 (invisible, score-based).
 *
 * No widget the shopper interacts with: captcha.js runs grecaptcha.execute() on
 * submit, drops a token into a hidden g-recaptcha-response field, and Google
 * returns a 0.0-1.0 score. We reject anything below the admin's threshold.
 */
class RecaptchaV3Provider extends TokenVerifyProvider
{
    public function key(): string
    {
        return 'recaptcha_v3';
    }

    public function label(): string
    {
        return 'Google reCAPTCHA v3';
    }

    public function description(): string
    {
        return 'Invisible and score-based. No shopper interaction; you set the pass threshold.';
    }

    protected function verifyUrl(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }

    protected function siteKeyName(): string
    {
        return 'captcha_recaptcha_v3_site_key';
    }

    protected function secretKeyName(): string
    {
        return 'captcha_recaptcha_v3_secret_key';
    }

    public function responseField(): string
    {
        return 'g-recaptcha-response';
    }

    protected function containerClass(): string
    {
        // v3 has no container; the token is fetched by JS. Unused.
        return 'g-recaptcha-v3';
    }

    protected function scriptUrl(): string
    {
        // The render param loads the badge and enables grecaptcha.execute().
        return 'https://www.google.com/recaptcha/api.js?render='.urlencode((string) $this->siteKey());
    }

    public function widgetConfig(): array
    {
        return [
            'container_class' => $this->containerClass(),
            'script_url' => $this->scriptUrl(),
            'response_field' => $this->responseField(),
            'needs_execute' => true,          // captcha.js runs it on submit
            'site_key' => $this->siteKey(),
        ];
    }

    /**
     * v3 adds a score gate on top of the success check. A well-formed token from
     * a bot still verifies as success:true but scores low; the threshold, not
     * success alone, is the gate. A below-threshold score is a real rejection,
     * not an outage, so it fails rather than fails-open.
     */
    protected function evaluate(array $data): CaptchaResult
    {
        if (($data['success'] ?? false) !== true) {
            $codes = $data['error-codes'] ?? [];
            $reason = is_array($codes) && $codes ? implode(',', $codes) : 'rejected';

            return CaptchaResult::fail($reason);
        }

        $score = isset($data['score']) ? (float) $data['score'] : null;
        $threshold = CaptchaSettings::v3Threshold();

        if ($score === null) {
            return CaptchaResult::pass();
        }

        if ($score < $threshold) {
            return CaptchaResult::fail('low_score', $score);
        }

        return CaptchaResult::pass($score);
    }
}
