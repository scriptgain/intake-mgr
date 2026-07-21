<?php

namespace App\Services\Captcha;

/**
 * The outcome of a single provider's server-side verification.
 *
 * Three states, not two, because the fail policy needs to tell "the provider
 * said no" apart from "the provider could not be reached":
 *
 *   - pass        : verified human. Always allowed through.
 *   - fail        : the provider answered and rejected the token (missing,
 *                   forged, expired, replayed, or below the v3 score). Always
 *                   rejected, regardless of fail policy: a definite "no" is not
 *                   an outage.
 *   - unreachable : the provider's siteverify endpoint threw or timed out. This
 *                   is the ONLY state the fail policy governs: fail closed on a
 *                   login, fail open on a plain contact form.
 *
 * $score is carried only for reCAPTCHA v3 so the audit note can record it.
 */
class CaptchaResult
{
    public function __construct(
        public bool $ok,
        public bool $reachable = true,
        public ?string $reason = null,
        public ?float $score = null,
    ) {}

    public static function pass(?float $score = null): self
    {
        return new self(ok: true, reachable: true, reason: null, score: $score);
    }

    /** The provider answered and said no. Not an outage; never fails open. */
    public static function fail(string $reason, ?float $score = null): self
    {
        return new self(ok: false, reachable: true, reason: $reason, score: $score);
    }

    /** The provider could not be reached. The fail policy decides from here. */
    public static function unreachable(string $reason): self
    {
        return new self(ok: false, reachable: false, reason: $reason, score: null);
    }
}
