<?php

namespace App\Http\Middleware;

use App\Services\Captcha\CaptchaManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level spam protection.
 *
 * Applied as `captcha:<surface>` to the POST route of each protected form, e.g.
 * `->middleware('captcha:account_register')`. Keeping it in the route file means
 * the existing controllers and their login throttle are untouched: the captcha
 * is complementary, checked before the request ever reaches the action.
 *
 * On a block it bounces straight back to the form with a field error and the
 * shopper's input preserved (minus passwords), so nothing about their session or
 * the existing rate limiter changes.
 */
class EnsureCaptcha
{
    public function __construct(private CaptchaManager $captcha) {}

    public function handle(Request $request, Closure $next, string $surface): Response
    {
        // Only meaningful on state-changing submits; never gate a GET render.
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $outcome = $this->captcha->verify($request, $surface);

        if ($outcome->passed) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $outcome->message,
                'errors' => ['captcha' => [$outcome->message]],
            ], 422);
        }

        return back()
            ->withErrors(['captcha' => $outcome->message])
            ->withInput($request->except(['password', 'password_confirmation', 'current_password']));
    }
}
