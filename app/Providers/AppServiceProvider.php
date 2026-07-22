<?php

namespace App\Providers;

use App\Models\Collection;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One cart per request: the storefront layout, the cart page, and the
        // checkout controller must all see the same instance.
        $this->app->singleton(CartService::class);

        // The SEO resolver carries per-request state (the controller describes
        // the page, the layout later asks for the result), so it must be the
        // same instance on both sides of a request.
        $this->app->singleton(\App\Services\SeoService::class);
        $this->app->singleton(\App\Services\SeoUrlService::class);
        $this->app->singleton(\App\Services\StructuredDataService::class);

        // Spam protection: one manager per request so the honeypot token issued
        // by the <x-captcha> component and the check done by the captcha
        // middleware share the same instance.
        $this->app->singleton(\App\Services\Captcha\Honeypot::class);
        $this->app->singleton(\App\Services\Captcha\CaptchaManager::class);
    }

    public function boot(): void
    {
        $this->applyDatabaseConfig();
        $this->composeStorefront();

        // API rate limit: per token, or per IP when unauthenticated.
        \Illuminate\Support\Facades\RateLimiter::for('api', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(
            (int) config('api.rate_limit', 120)
        )->by($request->user()?->id ? 'tok:'.$request->user()->id : 'ip:'.$request->ip()));
    }

    /**
     * Apply DB-backed settings over config at boot (the fleet's DB-driven config
     * pattern — secrets and per-store config live in the settings table, never
     * in .env). Guarded so the app still boots before migrations run.
     */
    private function applyDatabaseConfig(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $s = Setting::map();

            // DB-driven timezone: makes reports and order timestamps display in
            // the merchant's zone (e.g. America/Phoenix) instead of UTC.
            if (! empty($s['timezone'])) {
                config(['app.timezone' => $s['timezone']]);
                date_default_timezone_set($s['timezone']);
            }

            // Branding.
            if (! empty($s['brand_name'])) {
                config(['brand.name' => $s['brand_name'], 'app.name' => $s['brand_name']]);
            }
            if (! empty($s['brand_tagline'])) {
                config(['brand.tagline' => $s['brand_tagline']]);
            }
            if (! empty($s['brand_accent'])) {
                config(['brand.accent' => $s['brand_accent']]);
            }

            // Idle session timeout (minutes) -> Laravel session lifetime. Live.
            if (! empty($s['session_timeout_minutes'])) {
                config(['session.lifetime' => (int) $s['session_timeout_minutes']]);
            }

            // Store configuration. Every one of these is editable at
            // Settings -> Storefront / Payments; config/shop.php only supplies
            // the fallback for a store that has not been configured yet.
            config([
                'shop.max_width' => $s['max_width'] ?? config('shop.max_width'),

                'shop.store_name' => $s['store_name'] ?? config('brand.name'),
                'shop.store_tagline' => $s['store_tagline'] ?? config('shop.store_tagline'),
                'shop.store_email' => $s['store_email'] ?? null,
                'shop.store_phone' => $s['store_phone'] ?? null,
                'shop.store_address' => $s['store_address'] ?? null,

                'shop.currency' => $s['currency'] ?? config('shop.currency'),
                'shop.currency_symbol' => $s['currency_symbol'] ?? config('shop.currency_symbol'),
                'shop.currency_decimals' => (int) ($s['currency_decimals'] ?? config('shop.currency_decimals')),

                'shop.products_per_page' => (int) ($s['products_per_page'] ?? config('shop.products_per_page')),
                'shop.low_stock_threshold' => (int) ($s['low_stock_threshold'] ?? config('shop.low_stock_threshold')),
                'shop.allow_backorder' => ($s['allow_backorder'] ?? '0') === '1',
                'shop.hide_out_of_stock' => ($s['hide_out_of_stock'] ?? '0') === '1',

                'shop.guest_checkout' => ($s['guest_checkout'] ?? '1') === '1',
                'shop.terms_required' => ($s['terms_required'] ?? '1') === '1',
                'shop.order_prefix' => $s['order_prefix'] ?? config('shop.order_prefix'),

                'shop.tax_mode' => $s['tax_mode'] ?? config('shop.tax_mode'),
                'shop.tax_shipping' => ($s['tax_shipping'] ?? '0') === '1',

                'shop.payments.default_gateway' => $s['default_gateway'] ?? config('shop.payments.default_gateway'),
                'shop.payments.stripe_mode' => $s['stripe_mode'] ?? config('shop.payments.stripe_mode'),

                // Rows-per-page + formats for the admin's paginated tables.
                'shop.rows_per_page' => (int) ($s['rows_per_page'] ?? 25),
                'shop.require_2fa' => ($s['require_2fa'] ?? '0') === '1',
                'shop.force_password_days' => (int) ($s['force_password_days'] ?? 0),
                'shop.audit_log_days' => (int) ($s['audit_log_days'] ?? 180),
                'shop.date_format' => $s['date_format'] ?? 'M j, Y',
                'shop.time_format' => $s['time_format'] ?? 'g:i A',
            ]);

            // SEO. Same rule as the rest: config/seo.php holds defaults only,
            // Settings -> SEO is the source of truth.
            config([
                'seo.site_noindex' => ($s['seo_site_noindex'] ?? '0') === '1',
                'seo.title_template' => $s['seo_title_template'] ?? config('seo.title_template'),
                'seo.default_description' => $s['seo_default_description'] ?? null,
                'seo.default_og_image' => $s['seo_default_og_image'] ?? null,
                'seo.twitter_card' => $s['seo_twitter_card'] ?? config('seo.twitter_card'),
                'seo.twitter_site' => $s['seo_twitter_site'] ?? null,
                'seo.organization_name' => $s['seo_organization_name'] ?? null,
                'seo.organization_logo' => $s['seo_organization_logo'] ?? null,
                'seo.organization_sameas' => $s['seo_organization_sameas'] ?? null,
                'seo.item_condition' => $s['seo_item_condition'] ?? config('seo.item_condition'),
                'seo.sitemap_include_out_of_stock' => ($s['seo_sitemap_include_out_of_stock'] ?? '1') === '1',
                'seo.google_verification' => $s['seo_google_verification'] ?? null,
                'seo.bing_verification' => $s['seo_bing_verification'] ?? null,
            ]);

            // Payment gateway credentials — DB only, never .env.
            config([
                'services.stripe.key' => $s['stripe_publishable_key'] ?? null,
                'services.stripe.secret' => $s['stripe_secret_key'] ?? null,
                'services.stripe.webhook_secret' => $s['stripe_webhook_secret'] ?? null,
            ]);

            // DB-driven SMTP for order confirmations + staff notifications.
            if (! empty($s['smtp_host'])) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $s['smtp_host'],
                    'mail.mailers.smtp.port' => (int) ($s['smtp_port'] ?: 587),
                    'mail.mailers.smtp.username' => $s['smtp_username'] ?? null,
                    'mail.mailers.smtp.password' => $s['smtp_password'] ?? null,
                    'mail.from.address' => $s['mail_from'] ?: ('orders@'.parse_url(config('app.url'), PHP_URL_HOST)),
                    'mail.from.name' => $s['store_name'] ?? config('brand.name'),
                ]);
            }
        } catch (\Throwable $e) {
            // DB not ready (e.g. during install); fall back to config defaults.
        }
    }

    /**
     * Share the storefront chrome's data with every shop view.
     *
     * This is the "no PHP logic in views" rule in practice: the header needs the
     * nav collections and a live cart count on every page, and a composer is
     * where that belongs — not a @php block in the layout.
     */
    private function composeStorefront(): void
    {
        View::composer(['components.layouts.shop', 'shop.*'], function ($view) {
            $defaults = [
                'navCollections' => collect(),
                'cartCount' => 0,
                'storeName' => config('shop.store_name'),
                'currentCustomer' => null,
                'maxWidth' => config('shop.max_width', 'max-w-6xl'),
                'themeLogo' => null,
            ];

            try {
                if (! Schema::hasTable('collections')) {
                    $view->with($defaults);

                    return;
                }

                $cart = app(CartService::class)->current(create: false);

                $view->with([
                    'navCollections' => Collection::active()
                        ->orderBy('position')
                        ->orderBy('name')
                        ->limit(6)
                        ->get(),
                    'cartCount' => $cart?->item_count ?? 0,
                    'storeName' => config('shop.store_name'),
                    'currentCustomer' => auth('customer')->user(),
                    'maxWidth' => config('shop.max_width', 'max-w-6xl'),
                    // The active theme may replace the wordmark with an
                    // uploaded logo. Resolved here, not in the layout.
                    'themeLogo' => app(\App\Services\ThemeService::class)->active()?->logoUrl(),
                ]);
            } catch (\Throwable $e) {
                $view->with($defaults);
            }
        });
    }
}
