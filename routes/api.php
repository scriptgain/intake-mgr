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

/*
 * Authorize.Net webhook. Same rationale as the Stripe one: API stack, no
 * session/CSRF, no storefront gates. The X-ANET-Signature HMAC-SHA512 is the
 * credential, verified inside the controller.
 */
Route::post('authnet/webhook', \App\Http\Controllers\AuthorizeNetWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('authnet.webhook');

// Merchant REST API. Bearer token (api_tokens) auth. Base: /api/v1
// Route names are prefixed with "api." so they never collide with the web
// resource route names (products.*, orders.*, customers.*).
Route::prefix('v1')->name('api.')->middleware(['api.token', 'throttle:api'])->group(function () {
    Route::get('me', fn (Request $r) => $r->user()->only(['id', 'name', 'email', 'role']));

    // Catalog + commerce read/write, mirroring the admin controllers.
    Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class);
    Route::apiResource('collections', \App\Http\Controllers\Api\CollectionController::class);
    Route::apiResource('orders', \App\Http\Controllers\Api\OrderController::class)->only(['index', 'show', 'update']);
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class);
    Route::apiResource('discounts', \App\Http\Controllers\Api\DiscountController::class);

    /* ---- Service desk ---- */
    Route::post('service-requests/{serviceRequest}/convert-ticket', [\App\Http\Controllers\Api\ServiceRequestController::class, 'convertTicket']);
    Route::post('service-requests/{serviceRequest}/convert-work-order', [\App\Http\Controllers\Api\ServiceRequestController::class, 'convertWorkOrder']);
    Route::post('service-requests/{serviceRequest}/close', [\App\Http\Controllers\Api\ServiceRequestController::class, 'close']);
    Route::apiResource('service-requests', \App\Http\Controllers\Api\ServiceRequestController::class)->parameters(['service-requests' => 'serviceRequest']);

    Route::get('tickets/{ticket}/replies', [\App\Http\Controllers\Api\TicketController::class, 'replies']);
    Route::post('tickets/{ticket}/replies', [\App\Http\Controllers\Api\TicketController::class, 'reply']);
    Route::post('tickets/{ticket}/status', [\App\Http\Controllers\Api\TicketController::class, 'status']);
    Route::post('tickets/{ticket}/assign', [\App\Http\Controllers\Api\TicketController::class, 'assign']);
    Route::post('tickets/{ticket}/work-order', [\App\Http\Controllers\Api\TicketController::class, 'workOrder']);
    Route::apiResource('tickets', \App\Http\Controllers\Api\TicketController::class);

    Route::post('work-orders/{workOrder}/status', [\App\Http\Controllers\Api\WorkOrderController::class, 'status']);
    Route::post('work-orders/{workOrder}/complete', [\App\Http\Controllers\Api\WorkOrderController::class, 'complete']);
    Route::post('work-orders/{workOrder}/cancel', [\App\Http\Controllers\Api\WorkOrderController::class, 'cancel']);
    Route::post('work-orders/{workOrder}/reschedule', [\App\Http\Controllers\Api\WorkOrderController::class, 'reschedule']);
    Route::apiResource('work-orders', \App\Http\Controllers\Api\WorkOrderController::class)->parameters(['work-orders' => 'workOrder']);

    Route::post('projects/{project}/status', [\App\Http\Controllers\Api\ProjectController::class, 'status']);
    Route::apiResource('projects', \App\Http\Controllers\Api\ProjectController::class);

    Route::apiResource('booking-types', \App\Http\Controllers\Api\BookingTypeController::class)->parameters(['booking-types' => 'bookingType']);

    Route::get('users/{user}/availability', [\App\Http\Controllers\Api\AvailabilityController::class, 'show']);
    Route::put('users/{user}/availability', [\App\Http\Controllers\Api\AvailabilityController::class, 'update']);

    Route::apiResource('invoices', \App\Http\Controllers\Api\InvoiceController::class)->only(['index', 'show']);
    Route::apiResource('services', \App\Http\Controllers\Api\ServiceController::class)->only(['index', 'show']);

    // Access & administration.
    Route::apiResource('users', UserController::class);
    Route::apiResource('api-tokens', ApiTokenController::class)->only(['index', 'store', 'destroy'])->parameters(['api-tokens' => 'apiToken']);
});
