<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Stripe webhook.
 *
 * Deliberately on the API stack, not the web one. It needs no session, no CSRF
 * token (Stripe cannot supply one), and must not pass through the storefront's
 * setup gate, demo-mode guard or IP firewall — all of which would turn a
 * legitimate Stripe delivery into a redirect or a 403 and start a retry storm.
 *
 * Authentication is the Stripe-Signature HMAC, verified inside the controller.
 * There is no token on this route because the signature IS the credential.
 *
 * Rate limited generously: Stripe can burst on retries, and throttling a real
 * webhook into a 429 just makes it come back again.
 */
Route::post('stripe/webhook', \App\Http\Controllers\StripeWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('stripe.webhook');

// Merchant REST API. Bearer token (api_tokens) auth. Base: /api/v1
// Route names are prefixed with "api." so they never collide with the web
// resource route names (products.*, orders.*, customers.*).
Route::prefix('v1')->name('api.')->middleware('api.token')->group(function () {
    Route::get('me', fn (Request $r) => $r->user()->only(['id', 'name', 'email', 'role']));

    // Catalog + commerce read/write, mirroring the admin controllers.
    Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class);
    Route::apiResource('collections', \App\Http\Controllers\Api\CollectionController::class);
    Route::apiResource('orders', \App\Http\Controllers\Api\OrderController::class)->only(['index', 'show', 'update']);
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class);
    Route::apiResource('discounts', \App\Http\Controllers\Api\DiscountController::class);

    // Access & administration.
    Route::apiResource('users', UserController::class);
    Route::apiResource('api-tokens', ApiTokenController::class)->only(['index', 'store', 'destroy'])->parameters(['api-tokens' => 'apiToken']);
});
