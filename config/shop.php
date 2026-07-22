<?php

/*
|--------------------------------------------------------------------------
| ShopMGR store configuration
|--------------------------------------------------------------------------
| These are DEFAULTS ONLY. Everything here is overridden at boot from DB
| settings (AppServiceProvider + the Setting model), per the fleet's DB-driven
| config rule: secrets and per-store config never live in .env.
*/

return [
    // Layout width token used by every section wrapper. Views must read this
    // via {{ $maxWidth ?? 'max-w-6xl' }} and never hardcode a max-w-* class.
    'max_width' => env('SHOP_MAX_WIDTH', 'max-w-7xl'),

    // Storefront identity (Settings -> Storefront overrides all of these).
    'store_name' => 'IntakeMGR',
    'store_tagline' => 'Local Service, Handled',
    'store_email' => null,
    'store_phone' => null,
    'store_address' => null,
    // Business default timezone for scheduling/availability (per-staff can override).
    'timezone' => env('SHOP_TIMEZONE', 'America/Phoenix'),

    // Money. ShopMGR stores every amount as an integer in the currency's minor
    // unit (cents) to avoid float drift; `currency_decimals` drives display.
    'currency' => 'USD',
    'currency_symbol' => '$',
    'currency_decimals' => 2,

    // Catalog behaviour.
    'products_per_page' => 12,
    'low_stock_threshold' => 5,
    // When true, a variant with zero inventory can still be ordered.
    'allow_backorder' => false,
    // Hide out-of-stock products from storefront listings entirely.
    'hide_out_of_stock' => false,

    // Checkout behaviour.
    'guest_checkout' => true,
    'terms_required' => true,
    // Order numbers: <prefix><zero-padded sequence>, e.g. SM-1042.
    'order_prefix' => 'SM-',
    'order_start_number' => 1000,

    // Tax. 'exclusive' adds tax at checkout; 'inclusive' means listed prices
    // already contain it. Rules are per-region rows in the tax_rules table.
    'tax_mode' => 'exclusive',
    'tax_shipping' => false,

    // Payment gateways. Keys live in DB settings, never .env.
    'payments' => [
        // 'manual' = offline/bank-transfer/COD; always available as a fallback
        // so a fresh install can take an order before any gateway is wired.
        'default_gateway' => 'manual',
        'gateways' => ['manual', 'stripe', 'authorizenet'],
        // Stripe runs in test mode until a live key is saved in Settings.
        'stripe_mode' => 'test',
    ],

    // Read-only public demo. When true the panel auto-signs-in a demo user and
    // blocks every write so anyone can click around a seeded store safely.
    // Set DEMO_MODE=true only on a dedicated demo host; never on a real store.
    'demo' => (bool) env('DEMO_MODE', false),
];
