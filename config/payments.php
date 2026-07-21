<?php

/*
|--------------------------------------------------------------------------
| ShopMGR payments
|--------------------------------------------------------------------------
| Transport-level knobs only. Every credential lives in the settings table
| (Settings -> Payments), never here and never in .env, per the fleet's
| DB-driven config rule. See App\Services\Payments\PaymentSettings.
*/

return [
    'stripe' => [
        'base_uri' => 'https://api.stripe.com',

        // Pinned deliberately. Stripe changes response shapes between versions;
        // pinning means an account-level API upgrade in the Stripe dashboard
        // cannot silently change what this code receives.
        'version' => '2024-06-20',

        // Seconds. Long enough for a 3DS-heavy intent create, short enough that
        // a hung Stripe does not hold a checkout request open indefinitely.
        'timeout' => 20,

        // Signature replay window, seconds. Stripe's own default.
        'webhook_tolerance' => 300,
    ],

    // Stripe rejects card charges below this in USD. Checked before we bother
    // creating an intent so the shopper gets a sentence, not a gateway error.
    'minimum_charge_cents' => 50,

    // How long an unfinished PaymentIntent is reused rather than replaced.
    'intent_reuse_minutes' => 60,
];
