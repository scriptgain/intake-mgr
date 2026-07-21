<?php

// Branding. Rename the whole product from one place. These defaults can be
// overridden by env, and by DB settings applied at boot (the Branding settings
// screen) — matching the DB-driven config pattern.
return [
    'name' => env('BRAND_NAME', env('APP_NAME', 'IntakeMGR')),
    'tagline' => env('BRAND_TAGLINE', 'Self-Hosted Service Intake'),
    // Accent hex; overrides the brand ramp at runtime. Settable in the UI.
    // Orange is distinct from the rest of the -MGR fleet (rose/navy+gold/cyan/
    // amber/emerald/sky/indigo/bronze) and reads as an energetic service brand.
    'accent' => env('BRAND_ACCENT', '#ea580c'),
    // Logo/favicon glyph (an x-icon name). Distinct per product.
    'icon' => env('BRAND_ICON', 'bolt'),
];
