<?php

namespace App\Services\Captcha\Providers;

use App\Services\Captcha\CaptchaProvider;
use App\Services\Captcha\CaptchaResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The built-in "our own" challenge. No third party, no keys, works out of the box.
 *
 * A rotating question (simple arithmetic or a word task) whose answer is bound
 * into a server-signed token, so it can be neither forged nor usefully replayed:
 *
 *   token = issuedAt . nonce . HMAC_SHA256(issuedAt|nonce|answer, APP_KEY)
 *
 *   - FORGE: an attacker who wants a token for an answer they choose would need
 *     APP_KEY to sign it. They don't have it, so they can only ever submit the
 *     answer to the question they were actually served, which is the point.
 *   - REPLAY: the token carries its issue time and is single-use. It is rejected
 *     outside a short window, and its nonce is burned on first success, so a
 *     captured token cannot be posted twice.
 *
 * Stateless by default (the signature is self-contained); the single-use burn
 * is best-effort over the cache and never blocks a legitimate submit if the
 * cache is unavailable.
 */
class BuiltinProvider implements CaptchaProvider
{
    /** Token is valid for 20 minutes. Long enough to fill a form, short enough
     *  that a scraped token is stale before it is worth replaying. */
    private const TTL_SECONDS = 1200;

    public const FIELD_TOKEN = 'captcha_challenge';

    public const FIELD_ANSWER = 'captcha_answer';

    public function key(): string
    {
        return 'builtin';
    }

    public function label(): string
    {
        return 'Built-In Challenge';
    }

    public function description(): string
    {
        return 'A rotating question signed on the server. No third party, no keys, works immediately.';
    }

    public function isThirdParty(): bool
    {
        return false;
    }

    /** Always ready: it needs no external credentials. */
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
        return 'components.captcha.builtin';
    }

    /*
    |--------------------------------------------------------------------------
    | Challenge generation
    |--------------------------------------------------------------------------
    */

    /**
     * Produce a fresh question and its signed token for rendering.
     *
     * @return array{question: string, token: string}
     */
    public function issue(): array
    {
        [$question, $answer] = $this->makeChallenge();

        $issuedAt = time();
        $nonce = Str::random(16);
        $token = $issuedAt.'.'.$nonce.'.'.$this->sign($issuedAt, $nonce, $answer);

        return ['question' => $question, 'token' => $token];
    }

    /**
     * Build one challenge, returning [displayed question, normalized answer].
     * A small rotation of shapes so a scraper can't hardcode a single answer.
     */
    private function makeChallenge(): array
    {
        $shapes = ['add', 'subtract', 'multiply', 'word_count', 'word_pick', 'color'];
        $shape = $shapes[random_int(0, count($shapes) - 1)];

        return match ($shape) {
            'add' => (function () {
                $a = random_int(2, 9);
                $b = random_int(2, 9);

                return ['What is '.$a.' plus '.$b.'?', (string) ($a + $b)];
            })(),
            'subtract' => (function () {
                $a = random_int(6, 15);
                $b = random_int(1, 5);

                return ['What is '.$a.' minus '.$b.'?', (string) ($a - $b)];
            })(),
            'multiply' => (function () {
                $a = random_int(2, 6);
                $b = random_int(2, 6);

                return ['What is '.$a.' times '.$b.'?', (string) ($a * $b)];
            })(),
            'word_count' => (function () {
                $words = ['apple', 'river', 'pencil', 'garden', 'silver'];
                shuffle($words);
                $set = array_slice($words, 0, 3);

                return ['How many words are in this list: '.implode(', ', $set).'?', '3'];
            })(),
            'word_pick' => (function () {
                $words = ['orange', 'castle', 'yellow', 'planet'];
                shuffle($words);
                $pick = $words[0];

                return ['Type this word: '.strtoupper($pick), $this->normalize($pick)];
            })(),
            'color' => (function () {
                $colors = ['red', 'blue', 'green'];
                $pick = $colors[random_int(0, count($colors) - 1)];

                return ['The sky on a clear day is often this color; type it: '.strtoupper($pick), $this->normalize($pick)];
            })(),
        };
    }

    /**
     * A real end-to-end round trip for the admin "test this configuration"
     * button: issue a challenge with a known answer, then prove a correct answer
     * verifies and a wrong one is rejected. Uses no cache burn (fresh nonces).
     *
     * @return array{ok: bool, detail: string}
     */
    public function selfTest(): array
    {
        $issuedAt = time();
        $answer = (string) random_int(2, 9);

        // Two tokens with known nonces, so both sides of the round trip line up.
        $nonceGood = Str::random(16);
        $goodToken = $issuedAt.'.'.$nonceGood.'.'.$this->sign($issuedAt, $nonceGood, $answer);
        $correct = $this->verifyPair($goodToken, $answer);

        $nonceBad = Str::random(16);
        $badToken = $issuedAt.'.'.$nonceBad.'.'.$this->sign($issuedAt, $nonceBad, $answer);
        $wrong = $this->verifyPair($badToken, $answer === '9' ? '8' : '9');

        $ok = $correct->ok && ! $wrong->ok;

        return [
            'ok' => $ok,
            'detail' => $ok
                ? 'Round-trip verified: a correct answer passed and a wrong answer was rejected.'
                : 'Self-test failed (correct='.($correct->ok ? 'pass' : 'fail').', wrong='.($wrong->ok ? 'pass' : 'fail').').',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    */

    public function verify(Request $request): CaptchaResult
    {
        $token = (string) $request->input(self::FIELD_TOKEN, '');
        $answer = $this->normalize((string) $request->input(self::FIELD_ANSWER, ''));

        return $this->verifyPair($token, $answer);
    }

    /** Shared by verify() and the admin "test this configuration" round-trip. */
    public function verifyPair(string $token, string $answer): CaptchaResult
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return CaptchaResult::fail('malformed_token');
        }

        [$issuedAt, $nonce, $sig] = $parts;

        if (! ctype_digit($issuedAt) || $nonce === '' || $sig === '') {
            return CaptchaResult::fail('malformed_token');
        }

        $issuedAt = (int) $issuedAt;

        // Expired, or issued in the future (clock-skew tolerant by a minute).
        if ($issuedAt > time() + 60 || (time() - $issuedAt) > self::TTL_SECONDS) {
            return CaptchaResult::fail('expired');
        }

        if ($answer === '') {
            return CaptchaResult::fail('no_answer');
        }

        // Constant-time compare against the answer the shopper actually typed:
        // the signature only matches if that answer equals the one baked in at
        // issue time. Wrong answer => different digest => hash_equals false.
        $expected = $this->sign($issuedAt, $nonce, $answer);

        if (! hash_equals($expected, $sig)) {
            return CaptchaResult::fail('wrong_answer');
        }

        // Single-use: burn the nonce so a correct (token, answer) pair cannot be
        // replayed inside its validity window. Best-effort; a cache miss must not
        // block a genuine first submit.
        try {
            $cacheKey = 'captcha:builtin:used:'.hash('sha256', $nonce);

            if (Cache::has($cacheKey)) {
                return CaptchaResult::fail('replayed');
            }

            Cache::put($cacheKey, 1, self::TTL_SECONDS + 60);
        } catch (\Throwable $e) {
            // Cache unavailable: fall through on the signature + TTL guarantees.
        }

        return CaptchaResult::pass();
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function sign(int $issuedAt, string $nonce, string $answer): string
    {
        return hash_hmac('sha256', $issuedAt.'|'.$nonce.'|'.$answer, (string) config('app.key'));
    }

    /** Case- and space-insensitive so "Blue " and "blue" both pass. */
    private function normalize(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}
