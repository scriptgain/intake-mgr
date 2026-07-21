<?php

namespace App\Services\Captcha;

/**
 * The always-on baseline: a honeypot field plus a time-trap. Runs on every
 * protected surface independent of the chosen provider, even when the provider
 * is "none", so a form is never left unprotected and never broken by missing
 * keys.
 *
 *   HONEYPOT  a hidden field a human never sees (aria-hidden, positioned
 *             off-screen). Many bots fill every field they find; a non-empty
 *             value is a bot.
 *
 *   TIME-TRAP a server-signed timestamp planted when the form renders. A submit
 *             that arrives faster than a human could plausibly type was
 *             automated. The timestamp is HMAC-signed so it cannot be back-dated
 *             to defeat the check.
 *
 * Both are local checks with no third party, so they always "fail closed": a
 * tripped honeypot or an impossibly fast submit is a definite bot, not an
 * outage, and there is nothing to fail open to.
 */
class Honeypot
{
    /** The bait field's name. Innocuous enough that bots fill it, unused by any
     *  real form on these surfaces. */
    public const FIELD = 'website';

    /** The signed time-trap token field. */
    public const TS_FIELD = 'form_started_at';

    /** A fresh signed timestamp token to plant in the form. */
    public function issueToken(): string
    {
        $ts = time();

        return $ts.'.'.$this->sign($ts);
    }

    /**
     * @return array{ok: bool, reason: ?string}
     *
     * ok:false means "this looks automated". The caller rejects and logs the
     * reason; it never fails open, because these are not network checks.
     */
    public function check(\Illuminate\Http\Request $request): array
    {
        // 1) Honeypot: any value at all is a bot. A human cannot see the field.
        if (trim((string) $request->input(self::FIELD, '')) !== '') {
            return ['ok' => false, 'reason' => 'honeypot_filled'];
        }

        // 2) Time-trap.
        $minSeconds = CaptchaSettings::minSeconds();

        if ($minSeconds <= 0) {
            return ['ok' => true, 'reason' => null];
        }

        $token = (string) $request->input(self::TS_FIELD, '');
        $parts = explode('.', $token);

        if (count($parts) !== 2 || ! ctype_digit($parts[0])) {
            // The planted token is gone or malformed: a bot posting a bare
            // payload without rendering the form. Treat as automated.
            return ['ok' => false, 'reason' => 'missing_time_token'];
        }

        [$ts, $sig] = $parts;

        if (! hash_equals($this->sign((int) $ts), $sig)) {
            return ['ok' => false, 'reason' => 'forged_time_token'];
        }

        $elapsed = time() - (int) $ts;

        // Negative elapsed = a back-dated or future timestamp (only possible via
        // a forged token, but the signature already caught that) => reject.
        if ($elapsed < $minSeconds) {
            return ['ok' => false, 'reason' => 'submitted_too_fast'];
        }

        return ['ok' => true, 'reason' => null];
    }

    private function sign(int $ts): string
    {
        return hash_hmac('sha256', 'hp|'.$ts, (string) config('app.key'));
    }
}
