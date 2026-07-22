<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\Contracts\PaymentGateway;

/**
 * Stripe gateway: a thin adapter over the existing, proven OrderPayments +
 * StripeGateway code. It deliberately adds no new behaviour to the Stripe path
 * — it only presents that path through the PaymentGateway contract so the pay
 * screen and refund UI can treat providers uniformly.
 */
class StripePaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function isConfigured(): bool
    {
        return PaymentSettings::isConfigured();
    }

    public function isEnabled(): bool
    {
        return PaymentSettings::isEnabled();
    }

    public function startPayment(Order $order): array
    {
        $secret = OrderPayments::clientSecretFor($order);

        return [
            'ok' => $secret['ok'],
            'provider' => 'stripe',
            'settled' => $secret['settled'],
            'error' => $secret['error'],
            'data' => [
                'client_secret' => $secret['client_secret'],
                'publishable_key' => PaymentSettings::publishableKey(),
                'test_mode' => PaymentSettings::isTestMode(),
            ],
        ];
    }

    /** Stripe confirms client-side; nothing to charge server-side here. */
    public function completePayment(Order $order, array $input): array
    {
        $order = OrderPayments::syncFromStripe($order);

        return ['ok' => true, 'settled' => $order->is_paid, 'error' => null];
    }

    public function syncFromRemote(Order $order): Order
    {
        return OrderPayments::syncFromStripe($order);
    }

    public function refund(Order $order, ?int $amountCents, ?string $reason, ?User $staff): array
    {
        return OrderPayments::refund($order, $amountCents, $reason, $staff);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $sig = $headers['stripe-signature'][0] ?? ($headers['stripe-signature'] ?? null);
        $result = StripeGateway::verifySignature(
            $payload,
            is_array($sig) ? ($sig[0] ?? null) : $sig,
            PaymentSettings::webhookSecret()
        );

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'reason' => $result['reason'] ?? null,
            'event' => $result['ok'] ? json_decode($payload, true) : null,
        ];
    }
}
