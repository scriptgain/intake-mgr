<?php

namespace App\Services\Captcha\Providers;

use App\Services\Captcha\CaptchaProvider;
use App\Services\Captcha\CaptchaResult;
use App\Services\Captcha\CaptchaSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared transport for every "token" provider: reCAPTCHA, hCaptcha, Turnstile.
 *
 * They differ only in endpoint, key names, widget script and response field.
 * All three take the shopper's widget token, POST it with the secret to a
 * siteverify endpoint, and read back { success, error-codes, ... }. That common
 * shape lives here; concrete providers fill in the specifics.
 *
 * NEVER LOGS THE SECRET OR THE TOKEN. Only error codes and status are recorded,
 * at warning level, because these installs run LOG_LEVEL=error.
 */
abstract class TokenVerifyProvider implements CaptchaProvider
{
    /** Provider's siteverify endpoint. */
    abstract protected function verifyUrl(): string;

    /** Settings key holding the public site key. */
    abstract protected function siteKeyName(): string;

    /** Settings key holding the secret key (write-only). */
    abstract protected function secretKeyName(): string;

    /** The request field the widget puts its token in. */
    abstract public function responseField(): string;

    /** CSS class the vendor script hydrates into a widget. */
    abstract protected function containerClass(): string;

    /** The vendor widget <script> URL. */
    abstract protected function scriptUrl(): string;

    public function isThirdParty(): bool
    {
        return true;
    }

    public function siteKey(): ?string
    {
        return CaptchaSettings::nullIfBlank(CaptchaSettings::get($this->siteKeyName()));
    }

    protected function secretKey(): ?string
    {
        return CaptchaSettings::nullIfBlank(CaptchaSettings::get($this->secretKeyName()));
    }

    public function isConfigured(): bool
    {
        return $this->siteKey() !== null && $this->secretKey() !== null;
    }

    public function widgetView(): ?string
    {
        // null => the generic third-party renderer in <x-captcha> draws it.
        return null;
    }

    public function widgetConfig(): array
    {
        return [
            'container_class' => $this->containerClass(),
            'script_url' => $this->scriptUrl(),
            'response_field' => $this->responseField(),
            'needs_execute' => false,
            'site_key' => $this->siteKey(),
        ];
    }

    public function verify(Request $request): CaptchaResult
    {
        $token = trim((string) $request->input($this->responseField(), ''));

        if ($token === '') {
            // A missing token is a definite failure, not an outage: the widget
            // was never solved (or a bot skipped it). Never fails open.
            return CaptchaResult::fail('missing_token');
        }

        $secret = $this->secretKey();

        if ($secret === null) {
            // Selected as active without a secret. Treat as unreachable so the
            // fail policy governs rather than hard-blocking every visitor.
            return CaptchaResult::unreachable('no_secret_configured');
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->retry(1, 200, throw: false)
                ->post($this->verifyUrl(), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);

            if (! $response->successful()) {
                Log::warning('Captcha siteverify HTTP error', [
                    'provider' => $this->key(),
                    'status' => $response->status(),
                ]);

                return CaptchaResult::unreachable('http_'.$response->status());
            }

            $data = $response->json() ?? [];

            return $this->evaluate($data);
        } catch (\Throwable $e) {
            Log::warning('Captcha siteverify threw', [
                'provider' => $this->key(),
                'exception' => class_basename($e),
            ]);

            return CaptchaResult::unreachable('connection_error');
        }
    }

    /**
     * Turn the decoded siteverify body into a result. Overridden by reCAPTCHA v3
     * to add the score gate. success:false here is a real rejection (bad or
     * expired token), so it fails and never trips the fail-open path.
     */
    protected function evaluate(array $data): CaptchaResult
    {
        if (($data['success'] ?? false) === true) {
            return CaptchaResult::pass();
        }

        $codes = $data['error-codes'] ?? $data['error_codes'] ?? [];
        $reason = is_array($codes) && $codes ? implode(',', $codes) : 'rejected';

        return CaptchaResult::fail($reason);
    }
}
