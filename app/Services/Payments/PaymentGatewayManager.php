<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Services\Payments\Contracts\PaymentGateway;

/**
 * Resolves the payment gateway for an order or by key, and lists the gateways
 * a customer may choose from. The order state machine is provider-neutral; this
 * is the one place that maps a provider key to its implementation.
 */
class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const GATEWAYS = [
        'stripe' => StripePaymentGateway::class,
        'authorizenet' => AuthorizeNetPaymentGateway::class,
    ];

    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function get(string $key): ?PaymentGateway
    {
        if (! isset(self::GATEWAYS[$key])) {
            return null;
        }

        return $this->resolved[$key] ??= app(self::GATEWAYS[$key]);
    }

    /** The gateway that should handle this order (its recorded provider, else the default). */
    public function for(Order $order): ?PaymentGateway
    {
        $key = $order->payment_provider ?: $order->payment_gateway;

        if ($key && $this->get($key)) {
            return $this->get($key);
        }

        return $this->default();
    }

    /** The merchant's preferred enabled gateway. */
    public function default(): ?PaymentGateway
    {
        $preferred = (string) \App\Models\Setting::get('default_gateway', 'stripe');

        if ($this->get($preferred)?->isEnabled()) {
            return $this->get($preferred);
        }

        return $this->enabled()[0] ?? null;
    }

    /**
     * All gateways the customer may pay with right now.
     *
     * @return array<int, PaymentGateway>
     */
    public function enabled(): array
    {
        $out = [];
        foreach (array_keys(self::GATEWAYS) as $key) {
            $gateway = $this->get($key);
            if ($gateway && $gateway->isEnabled()) {
                $out[] = $gateway;
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys(self::GATEWAYS);
    }
}
