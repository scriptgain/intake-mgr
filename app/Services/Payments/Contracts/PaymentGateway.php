<?php

namespace App\Services\Payments\Contracts;

use App\Models\Order;
use App\Models\User;

/**
 * One payment provider. IntakeMGR ships Stripe (client-confirmed PaymentIntents)
 * and Authorize.Net (on-site Accept.js tokenization charged server-side). The
 * order state machine (financial_status, idempotent markPaid, refunded_cents,
 * signed pay URLs) is provider-neutral and lives in OrderPayments; a gateway is
 * only the transport that talks to the remote processor and the shape of data
 * the pay screen needs to render this provider's form.
 */
interface PaymentGateway
{
    /** Stable machine key stored on orders.payment_provider, e.g. 'stripe'. */
    public function key(): string;

    /** Human label for admin/pay-screen, e.g. 'Stripe' / 'Authorize.Net'. */
    public function label(): string;

    /** Credentials present for the active (test/sandbox|live) mode. */
    public function isConfigured(): bool;

    /** Enabled by the merchant AND configured. Re-checked every request. */
    public function isEnabled(): bool;

    /**
     * Everything the pay screen needs to render this provider's card form for
     * an order. Providers differ: Stripe returns a client_secret for Stripe.js;
     * Authorize.Net returns the Accept.js public keys. Never returns an amount
     * taken from the client — the amount is always $order->total_cents.
     *
     * @return array{ok:bool, provider:string, settled:bool, error:?string, data:array}
     */
    public function startPayment(Order $order): array;

    /**
     * Finish a payment from provider-specific client input (Authorize.Net posts
     * an Accept.js opaque token back to the server to be charged). Stripe
     * confirms client-side and completes via return/webhook, so its impl is a
     * no-op that just re-syncs.
     *
     * @return array{ok:bool, settled:bool, error:?string}
     */
    public function completePayment(Order $order, array $input): array;

    /** Re-read authoritative status from the processor and apply it. */
    public function syncFromRemote(Order $order): Order;

    /** Refund fully (null) or partially. @return array{ok:bool,error:?string,amount_cents:int} */
    public function refund(Order $order, ?int $amountCents, ?string $reason, ?User $staff): array;

    /**
     * Verify an inbound webhook and return its parsed event.
     *
     * @return array{ok:bool, reason:?string, event:?array}
     */
    public function verifyWebhook(string $payload, array $headers): array;
}
