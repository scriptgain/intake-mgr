<?php

use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentSettingsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\SpamProtectionController;
use App\Http\Controllers\Admin\StorefrontSettingsController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\GeneralSettingsController;
use App\Http\Controllers\HostSslController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\PaymentController;
use Illuminate\Routing\Middleware\ValidateSignature;
use App\Http\Controllers\Shop\RobotsController;
use App\Http\Controllers\Shop\SitemapController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ShopMGR routes
|--------------------------------------------------------------------------
| Two halves, deliberately separated:
|   - the public STOREFRONT at /            (name prefix "shop.")
|   - the merchant ADMIN at /admin          (scaffold names kept unprefixed so
|     every inherited settings view/controller from the -MGR scaffold keeps
|     working untouched)
*/

// First-run setup wizard. Not behind 'auth': step 1 (create admin) runs as a
// guest, step 2 (license) runs authed. Access is governed by EnsureSetup.
Route::prefix('setup')->group(function () {
    Route::get('/', [SetupController::class, 'index'])->name('setup.index');
    Route::post('/admin', [SetupController::class, 'storeAdmin'])->name('setup.admin');
    Route::post('/license', [SetupController::class, 'storeLicense'])->name('setup.license');
});

// Staff auth.
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'show'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'login'])->middleware(['throttle:10,1', 'captcha:admin_login']);
    // Developer quick login. The action 404s unless the request IP matches
    // the dev_login_ip setting, so this route is gated, not just hidden.
    Route::post('/admin/dev-login', [AuthController::class, 'devLogin'])->name('dev-login')->middleware('throttle:10,1');
    // Staff demo personas (Merchant Admin / Staff). IP-gated inside the action
    // and captcha-free, exactly like dev-login: a POST from any other address
    // 404s.
    Route::post('/admin/demo-login/{persona}', [AuthController::class, 'demoLoginStaff'])
        ->name('demo-login.staff')->middleware('throttle:10,1');
});
Route::get('/magic/{user}', [AuthController::class, 'magic'])->name('magic-login')->middleware('signed');
Route::get('/2fa', [AuthController::class, 'challenge'])->name('2fa.challenge');
Route::post('/2fa', [AuthController::class, 'challengeVerify'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Brand favicon, accent-tinted from DB-driven branding (public — browsers fetch
// it pre-login). Extension-less on purpose: CloudPanel nginx serves *.svg/*.png
// as static files and 404s before reaching PHP.
Route::get('/brand/favicon', [FaviconController::class, 'svg'])->name('favicon.svg');
Route::get('/brand/favicon-png', [FaviconController::class, 'faviconPng'])->name('favicon.png');
Route::get('/brand/favicon-apple', [FaviconController::class, 'appleIcon'])->name('favicon.apple');

/*
|--------------------------------------------------------------------------
| SEO endpoints (public, unauthenticated)
|--------------------------------------------------------------------------
| robots.txt is dynamic so the staging noindex switch and the sitemap URL come
| from DB settings. public/robots.txt must NOT exist, or nginx serves the
| static file and never reaches PHP.
*/
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-products-{page}.xml', [SitemapController::class, 'products'])
    ->whereNumber('page')->name('sitemap.products');
Route::get('/sitemap-collections-{page}.xml', [SitemapController::class, 'collections'])
    ->whereNumber('page')->name('sitemap.collections');

/*
|--------------------------------------------------------------------------
| Storefront (public)
|--------------------------------------------------------------------------
*/
Route::name('shop.')->group(function () {
    Route::get('/', [CatalogController::class, 'home'])->name('home');
    Route::get('/products', [CatalogController::class, 'index'])->name('catalog');
    Route::get('/search', [CatalogController::class, 'search'])->name('search');
    Route::get('/collections', [CatalogController::class, 'collections'])->name('collections');
    Route::get('/collections/{collection:slug}', [CatalogController::class, 'collection'])->name('collection');
    Route::get('/products/{product:slug}', [CatalogController::class, 'product'])->name('product');

    /*
     * Help Center + policy pages (merchant-managed).
     *
     * /help/search is declared BEFORE /help/{category:slug} so the literal
     * segment is not swallowed by the slug parameter. The article route is
     * scopeBindings() so {article:slug} must belong to {category:slug}.
     */
    Route::get('/help', [\App\Http\Controllers\Shop\HelpController::class, 'index'])->name('help');
    Route::get('/help/search', [\App\Http\Controllers\Shop\HelpController::class, 'search'])->name('help.search');
    Route::get('/help/{category:slug}', [\App\Http\Controllers\Shop\HelpController::class, 'category'])->name('help.category');
    Route::get('/help/{category:slug}/{article:slug}', [\App\Http\Controllers\Shop\HelpController::class, 'article'])
        ->scopeBindings()->name('help.article');

    Route::get('/pages/{page:slug}', [\App\Http\Controllers\Shop\PageController::class, 'show'])->name('page');

    // Public release notes (merchant-managed).

    // Cart.
    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/items/{item}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/discount', [CartController::class, 'applyDiscount'])->name('cart.discount');
    Route::delete('/cart/discount', [CartController::class, 'removeDiscount'])->name('cart.discount.remove');

    // Checkout.
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->name('checkout.quote');
    Route::post('/checkout', [CheckoutController::class, 'place'])->middleware(['throttle:20,1', 'captcha:checkout'])->name('checkout.place');
    /*
     * Card step. Signed so a guest returns to their OWN payment page after a
     * 3D Secure bounce without needing an account, while the order number in
     * the path stays un-walkable by anyone who did not receive the link.
     */
    Route::get('/checkout/{order:number}/payment', [PaymentController::class, 'show'])
        ->middleware('signed')->name('checkout.payment');

    /*
     * Stripe's return_url.
     *
     * ValidateSignature::except() is load-bearing: Stripe appends
     * payment_intent, payment_intent_client_secret and redirect_status to the
     * URL it redirects to, and a plain 'signed' middleware hashes the full query
     * string, so every real return would 403. Those three parameters are
     * excluded from the signature and are NOT read by the controller either: the
     * outcome is re-fetched from the Stripe API, so appending
     * redirect_status=succeeded by hand achieves nothing.
     */
    Route::get('/checkout/{order:number}/return', [PaymentController::class, 'return'])
        ->middleware(ValidateSignature::absolute(['payment_intent', 'payment_intent_client_secret', 'redirect_status']))
        ->name('checkout.return');

    // Signed so a guest reaches their confirmation without an account, but the
    // URL cannot be walked to read someone else's order.
    Route::get('/orders/{order:number}/confirmation', [CheckoutController::class, 'confirmation'])
        ->middleware('signed')->name('checkout.confirmation');

    // Customer accounts (the 'customer' guard, never the staff one).
    Route::middleware('guest:customer')->group(function () {
        Route::get('/account/login', [AccountController::class, 'showLogin'])->name('account.login');
        Route::post('/account/login', [AccountController::class, 'login'])->middleware(['throttle:10,1', 'captcha:account_login']);
        Route::get('/account/register', [AccountController::class, 'showRegister'])->name('account.register');
        Route::post('/account/register', [AccountController::class, 'register'])->middleware(['throttle:10,1', 'captcha:account_register']);
        // Password reset. Both POSTs are captcha- and throttle-guarded: the
        // forgot form is an email-enumeration / mail-flood surface, and the reset
        // POST is a token-guessing surface.
        Route::get('/account/forgot', [AccountController::class, 'showForgot'])->name('account.forgot');
        Route::post('/account/forgot', [AccountController::class, 'sendResetLink'])->middleware(['throttle:10,1', 'captcha:account_forgot']);
        Route::get('/account/reset/{token}', [AccountController::class, 'showReset'])->name('account.reset');
        Route::post('/account/reset', [AccountController::class, 'reset'])->name('account.reset.update')->middleware(['throttle:10,1', 'captcha:account_forgot']);
        // Customer demo personas. IP-gated inside the action and captcha-free,
        // the storefront twin of the admin dev-login.
        Route::post('/account/demo-login/{persona}', [AccountController::class, 'demoLogin'])
            ->name('account.demo-login')->middleware('throttle:10,1');
    });
    Route::middleware('auth:customer')->group(function () {
        Route::get('/account', [AccountController::class, 'index'])->name('account');
        Route::get('/account/orders/{order:number}', [AccountController::class, 'order'])->name('account.order');
        Route::get('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
        Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
        Route::get('/account/addresses', [AccountController::class, 'addresses'])->name('account.addresses');
        Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
        Route::put('/account/addresses/{address}', [AccountController::class, 'updateAddress'])->name('account.addresses.update');
        Route::delete('/account/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');
        Route::post('/account/logout', [AccountController::class, 'logout'])->name('account.logout');
    });
});

/*
|--------------------------------------------------------------------------
| Merchant admin
|--------------------------------------------------------------------------
| Scaffold route NAMES are intentionally unprefixed (dashboard, settings.*,
| products.*) so the inherited -MGR settings screens keep working verbatim.
*/
Route::prefix('admin')->middleware(['auth', 'security.policy'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    /* ---- Catalog ---- */
    // Bulk routes are declared BEFORE the resource so /products/bulk is not
    // swallowed by /products/{product}.
    Route::delete('products/bulk', [ProductController::class, 'bulkDestroy'])->name('products.bulk-destroy');
    Route::post('products/bulk-status', [ProductController::class, 'bulkStatus'])->name('products.bulk-status');
    Route::resource('products', ProductController::class);
    Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
    Route::post('products/{product}/images', [ProductController::class, 'storeImage'])->name('products.images.store');
    Route::delete('products/{product}/images/{image}', [ProductController::class, 'destroyImage'])->name('products.images.destroy');
    Route::put('products/{product}/inventory', [ProductController::class, 'updateInventory'])->name('products.inventory.update');

    Route::delete('collections/bulk', [CollectionController::class, 'bulkDestroy'])->name('collections.bulk-destroy');
    Route::resource('collections', CollectionController::class);

    /* ---- SEO health (catalog-level, not a settings screen) ---- */
    Route::get('seo', [\App\Http\Controllers\Admin\SeoHealthController::class, 'index'])->name('seo.index');

    /* ---- Orders ---- */
    Route::delete('orders/bulk', [OrderController::class, 'bulkDestroy'])->name('orders.bulk-destroy');
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/fulfill', [OrderController::class, 'fulfill'])->name('orders.fulfill');
    Route::post('orders/{order}/pay', [OrderController::class, 'markPaid'])->name('orders.pay');
    Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');
    Route::post('orders/{order}/resend-email', [OrderController::class, 'resendEmail'])->name('orders.resend-email');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/note', [OrderController::class, 'note'])->name('orders.note');

    /* ---- Customers ---- */
    Route::delete('customers/bulk', [CustomerController::class, 'bulkDestroy'])->name('customers.bulk-destroy');
    Route::resource('customers', CustomerController::class)->except(['create', 'store']);

    /* ---- Discounts ---- */
    Route::delete('discounts/bulk', [DiscountController::class, 'bulkDestroy'])->name('discounts.bulk-destroy');
    Route::resource('discounts', DiscountController::class);

    /* ---- Help Center (categories + articles) and policy pages ---- */
    Route::delete('help-categories/bulk', [\App\Http\Controllers\Admin\HelpCategoryController::class, 'bulkDestroy'])->name('help-categories.bulk-destroy');
    Route::resource('help-categories', \App\Http\Controllers\Admin\HelpCategoryController::class)->except('show');
    Route::delete('help-articles/bulk', [\App\Http\Controllers\Admin\HelpArticleController::class, 'bulkDestroy'])->name('help-articles.bulk-destroy');
    Route::resource('help-articles', \App\Http\Controllers\Admin\HelpArticleController::class)->except('show');
    Route::delete('store-pages/bulk', [\App\Http\Controllers\Admin\StorePageController::class, 'bulkDestroy'])->name('store-pages.bulk-destroy');
    Route::resource('store-pages', \App\Http\Controllers\Admin\StorePageController::class)->except('show');

    /* ---- Shipping (zones + their rates) ---- */
    Route::delete('shipping/bulk', [ShippingController::class, 'bulkDestroy'])->name('shipping.bulk-destroy');
    Route::get('shipping', [ShippingController::class, 'index'])->name('shipping.index');
    Route::get('shipping/create', [ShippingController::class, 'create'])->name('shipping.create');
    Route::post('shipping', [ShippingController::class, 'store'])->name('shipping.store');
    Route::get('shipping/{zone}/edit', [ShippingController::class, 'edit'])->name('shipping.edit');
    Route::put('shipping/{zone}', [ShippingController::class, 'update'])->name('shipping.update');
    Route::delete('shipping/{zone}', [ShippingController::class, 'destroy'])->name('shipping.destroy');
    Route::post('shipping/{zone}/rates', [ShippingController::class, 'storeRate'])->name('shipping.rates.store');
    Route::put('shipping/rates/{rate}', [ShippingController::class, 'updateRate'])->name('shipping.rates.update');
    Route::delete('shipping/rates/{rate}', [ShippingController::class, 'destroyRate'])->name('shipping.rates.destroy');

    /* ---- Tax ---- */
    Route::delete('taxes/bulk', [TaxController::class, 'bulkDestroy'])->name('taxes.bulk-destroy');
    Route::resource('taxes', TaxController::class)->parameters(['taxes' => 'taxRule']);

    /* ---- Appearance: themes + templates -------------------------------
    | Themes restyle the storefront from stored design tokens; templates let
    | an admin edit the real Blade behind any page. Bulk and preview routes are
    | declared before the {theme} / {view} routes so they are not swallowed by
    | the parameter segment.
    */
    Route::get('appearance/themes', [ThemeController::class, 'index'])->name('themes.index');
    Route::get('appearance/themes/create', [ThemeController::class, 'create'])->name('themes.create');
    Route::post('appearance/themes', [ThemeController::class, 'store'])->name('themes.store');
    Route::post('appearance/themes/import', [ThemeController::class, 'import'])->name('themes.import');
    Route::delete('appearance/themes/bulk', [ThemeController::class, 'bulkDestroy'])->name('themes.bulk-destroy');
    Route::post('appearance/themes/preview/stop', [ThemeController::class, 'stopPreview'])->name('themes.preview.stop');
    Route::get('appearance/themes/{theme}/edit', [ThemeController::class, 'edit'])->name('themes.edit');
    Route::put('appearance/themes/{theme}', [ThemeController::class, 'update'])->name('themes.update');
    Route::delete('appearance/themes/{theme}', [ThemeController::class, 'destroy'])->name('themes.destroy');
    Route::post('appearance/themes/{theme}/activate', [ThemeController::class, 'activate'])->name('themes.activate');
    Route::post('appearance/themes/{theme}/preview', [ThemeController::class, 'preview'])->name('themes.preview');
    Route::post('appearance/themes/{theme}/duplicate', [ThemeController::class, 'duplicate'])->name('themes.duplicate');
    Route::get('appearance/themes/{theme}/export', [ThemeController::class, 'export'])->name('themes.export');

    Route::get('appearance/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('appearance/templates/preview/stop', [TemplateController::class, 'stopPreview'])->name('templates.preview.stop');
    Route::get('appearance/templates/{view}', [TemplateController::class, 'edit'])->name('templates.edit')->where('view', '[A-Za-z0-9._-]+');
    Route::put('appearance/templates/{view}', [TemplateController::class, 'update'])->name('templates.update')->where('view', '[A-Za-z0-9._-]+');
    Route::delete('appearance/templates/{view}', [TemplateController::class, 'reset'])->name('templates.reset')->where('view', '[A-Za-z0-9._-]+');
    Route::post('appearance/templates/{view}/preview', [TemplateController::class, 'preview'])->name('templates.preview')->where('view', '[A-Za-z0-9._-]+');
    Route::get('appearance/templates/{view}/versions/{version}', [TemplateController::class, 'version'])->name('templates.version')->where('view', '[A-Za-z0-9._-]+');
    Route::post('appearance/templates/{view}/versions/{version}/revert', [TemplateController::class, 'revert'])->name('templates.revert')->where('view', '[A-Za-z0-9._-]+');

    /* ---- Settings: store-specific ---- */
    Route::view('/settings', 'settings.index')->name('settings.index');
    Route::get('settings/storefront', [StorefrontSettingsController::class, 'edit'])->name('settings.storefront.edit');
    Route::put('settings/storefront', [StorefrontSettingsController::class, 'update'])->name('settings.storefront.update');
    Route::get('settings/payments', [PaymentSettingsController::class, 'edit'])->name('settings.payments.edit');
    Route::put('settings/payments', [PaymentSettingsController::class, 'update'])->name('settings.payments.update');
    Route::post('settings/payments/test', [PaymentSettingsController::class, 'test'])->name('settings.payments.test');
    Route::get('settings/seo', [\App\Http\Controllers\Admin\SeoSettingsController::class, 'edit'])->name('settings.seo.edit');
    Route::put('settings/seo', [\App\Http\Controllers\Admin\SeoSettingsController::class, 'update'])->name('settings.seo.update');
    Route::get('settings/spam', [SpamProtectionController::class, 'edit'])->name('settings.spam.edit');
    Route::put('settings/spam', [SpamProtectionController::class, 'update'])->name('settings.spam.update');
    Route::post('settings/spam/test', [SpamProtectionController::class, 'test'])->name('settings.spam.test');

    /* ---- Settings: inherited -MGR scaffold ---- */
    Route::get('settings/tokens', [ApiTokenController::class, 'index'])->name('settings.tokens.index');
    Route::post('settings/tokens', [ApiTokenController::class, 'store'])->name('settings.tokens.store');
    Route::delete('settings/tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->name('settings.tokens.destroy');
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('settings.password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('settings.password.update');
    Route::get('settings/license', [\App\Http\Controllers\LicenseController::class, 'edit'])->name('settings.license.edit');
    Route::put('settings/license', [\App\Http\Controllers\LicenseController::class, 'update'])->name('settings.license.update');
    Route::post('settings/license/sync', [\App\Http\Controllers\LicenseController::class, 'sync'])->name('settings.license.sync');
    Route::get('settings/branding', [BrandingController::class, 'edit'])->name('settings.branding.edit');
    Route::put('settings/branding', [BrandingController::class, 'update'])->name('settings.branding.update');
    Route::get('settings/2fa', [TwoFactorController::class, 'show'])->name('settings.2fa.show');
    Route::post('settings/2fa/enable', [TwoFactorController::class, 'enable'])->name('settings.2fa.enable');
    Route::post('settings/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('settings.2fa.confirm');
    Route::delete('settings/2fa', [TwoFactorController::class, 'disable'])->name('settings.2fa.disable');
    Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('settings.notifications.edit');
    Route::put('settings/notifications', [NotificationController::class, 'update'])->name('settings.notifications.update');
    Route::post('settings/notifications/test', [NotificationController::class, 'test'])->name('settings.notifications.test');
    Route::get('settings/users', [UserController::class, 'index'])->name('settings.users.index');
    Route::get('settings/users/create', [UserController::class, 'create'])->name('settings.users.create');
    Route::post('settings/users', [UserController::class, 'store'])->name('settings.users.store');
    Route::get('settings/users/{user}/edit', [UserController::class, 'edit'])->name('settings.users.edit');
    Route::put('settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
    Route::delete('settings/users/{user}', [UserController::class, 'destroy'])->name('settings.users.destroy');
    Route::get('settings/audit', [AuditLogController::class, 'index'])->name('settings.audit.index');
    Route::delete('settings/audit/selected', [AuditLogController::class, 'destroySelected'])->name('settings.audit.destroy-selected');
    Route::delete('settings/audit/all', [AuditLogController::class, 'destroyAll'])->name('settings.audit.destroy-all');
    Route::get('settings/general', [GeneralSettingsController::class, 'edit'])->name('settings.general.edit');
    Route::put('settings/general', [GeneralSettingsController::class, 'update'])->name('settings.general.update');

    Route::get('settings/firewall', [FirewallController::class, 'index'])->name('settings.firewall.index');
    Route::put('settings/firewall', [FirewallController::class, 'update'])->name('settings.firewall.update');
    Route::post('settings/firewall/bans', [FirewallController::class, 'ban'])->name('settings.firewall.ban');
    Route::delete('settings/firewall/bans/{bannedIp}', [FirewallController::class, 'unban'])->name('settings.firewall.unban');
    Route::delete('settings/firewall/sessions/{id}', [FirewallController::class, 'revokeSession'])->name('settings.firewall.session.revoke');
    Route::post('settings/firewall/sessions/bulk', [FirewallController::class, 'bulkSessions'])->name('settings.firewall.sessions.bulk');
    Route::post('settings/firewall/bulk', [FirewallController::class, 'bulk'])->name('settings.firewall.bulk');

    Route::get('settings/host', [HostSslController::class, 'edit'])->name('settings.host.edit');
    Route::put('settings/host', [HostSslController::class, 'update'])->name('settings.host.update');
    Route::post('settings/host/letsencrypt', [HostSslController::class, 'letsencrypt'])->name('settings.host.letsencrypt');
    Route::post('settings/host/upload', [HostSslController::class, 'upload'])->name('settings.host.upload');
    Route::post('settings/host/self-signed', [HostSslController::class, 'selfSigned'])->name('settings.host.self-signed');

    Route::get('settings/integrations', [\App\Http\Controllers\IntegrationController::class, 'edit'])->name('settings.integrations.edit');
    Route::put('settings/integrations', [\App\Http\Controllers\IntegrationController::class, 'update'])->name('settings.integrations.update');
    Route::post('settings/integrations/test', [\App\Http\Controllers\IntegrationController::class, 'test'])->name('settings.integrations.test');

    Route::get('settings/backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('settings.backup.index');
    Route::get('settings/backup/config', [\App\Http\Controllers\BackupController::class, 'downloadConfig'])->name('settings.backup.config');
    Route::get('settings/backup/database', [\App\Http\Controllers\BackupController::class, 'downloadDatabase'])->name('settings.backup.database');
    Route::post('settings/backup/restore', [\App\Http\Controllers\BackupController::class, 'restore'])->name('settings.backup.restore');
    Route::put('settings/backup/schedule', [\App\Http\Controllers\BackupController::class, 'saveSchedule'])->name('settings.backup.schedule');
    Route::post('settings/backup/run', [\App\Http\Controllers\BackupController::class, 'runNow'])->name('settings.backup.run');

    Route::get('settings/updates', [\App\Http\Controllers\UpdateController::class, 'show'])->name('settings.updates.show');
    Route::post('settings/updates/check', [\App\Http\Controllers\UpdateController::class, 'check'])->name('settings.updates.check');
    Route::post('settings/updates/apply', [\App\Http\Controllers\UpdateController::class, 'apply'])->name('settings.updates.apply');
    Route::post('settings/updates/auto', [\App\Http\Controllers\UpdateController::class, 'toggleAuto'])->name('settings.updates.auto');
});
