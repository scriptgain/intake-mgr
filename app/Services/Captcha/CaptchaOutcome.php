<?php

namespace App\Services\Captcha;

/**
 * The aggregate verdict CaptchaManager hands back to a caller (middleware or
 * controller): did this request clear spam protection, and if not, what does the
 * shopper see and what goes in the audit log.
 */
class CaptchaOutcome
{
    public function __construct(
        public bool $passed,
        public ?string $message = null,
        public ?string $logReason = null,
        public bool $failOpenApplied = false,
    ) {}

    public static function pass(): self
    {
        return new self(passed: true);
    }

    public static function passFailOpen(string $logReason): self
    {
        return new self(passed: true, logReason: $logReason, failOpenApplied: true);
    }

    public static function block(string $message, string $logReason): self
    {
        return new self(passed: false, message: $message, logReason: $logReason);
    }
}
