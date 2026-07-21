<?php

/*
|--------------------------------------------------------------------------
| ShopMGR SEO configuration
|--------------------------------------------------------------------------
| DEFAULTS ONLY. Every value here is overridden at boot from DB settings
| (AppServiceProvider), per the fleet's DB-driven config rule. Edit them at
| Settings -> SEO, never in .env.
*/

return [
    // Site-wide staging switch. When true every storefront page emits
    // noindex,nofollow and robots.txt disallows the whole site. This is the one
    // switch that must survive a copy of a live store onto a staging host.
    'site_noindex' => false,

    // Title template. {title} is the page title, {store} the store name.
    // The separator is a colon, not an em dash.
    'title_template' => '{title}: {store}',

    // Length targets used by both the admin character counters and the SEO
    // health screen. These are the widely used SERP truncation points, not
    // hard limits, so the health screen reports them as warnings.
    'title_min' => 25,
    'title_max' => 60,
    'description_min' => 70,
    'description_max' => 160,

    // Fallbacks when an entity and its derived text both come up empty.
    'default_description' => null,
    'default_og_image' => null,

    // Social.
    'twitter_card' => 'summary_large_image',
    'twitter_site' => null,

    // Organization/WebSite JSON-LD on the home page.
    'organization_name' => null,   // falls back to the store name
    'organization_logo' => null,   // absolute URL
    'organization_sameas' => null, // newline-separated profile URLs

    // schema.org itemCondition applied to every Offer. A store selling used or
    // refurbished goods changes this once here rather than per product.
    'item_condition' => 'NewCondition',

    // Sitemap.
    'sitemap_chunk' => 2000,
    // When false, products with nothing purchasable are left out of the
    // sitemap. Ignored (forced false) when the storefront is configured to
    // hide out-of-stock products, since those URLs are not browsable at all.
    'sitemap_include_out_of_stock' => true,

    // Search console ownership tokens.
    'google_verification' => null,
    'bing_verification' => null,
];
