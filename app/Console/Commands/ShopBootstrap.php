<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Post-install bootstrap, run by the ScriptGain universal installer
 * (BOOTSTRAP_CMD="shop:bootstrap" in products/shop-mgr.env).
 *
 * Everything here is idempotent — the installer is safe to re-run, and so is
 * this command.
 */
class ShopBootstrap extends Command
{
    protected $signature = 'shop:bootstrap {--fresh-token : Issue a new full-access token even if one exists}';

    protected $description = 'Seed store defaults (settings, shipping zones) and issue a first full-access API token.';

    public function handle(): int
    {
        $this->seedSettings();
        $this->seedShipping();
        $this->linkStorage();
        $this->issueToken();

        $this->newLine();
        $this->info('ShopMGR is bootstrapped. Finish at /setup, then add products at /admin/products.');

        return self::SUCCESS;
    }

    /** Store defaults, written only when absent so a merchant's edits survive. */
    private function seedSettings(): void
    {
        $defaults = [
            'store_name' => config('brand.name'),
            'store_tagline' => config('shop.store_tagline'),
            'currency' => config('shop.currency'),
            'currency_symbol' => config('shop.currency_symbol'),
            'currency_decimals' => (string) config('shop.currency_decimals'),
            'products_per_page' => (string) config('shop.products_per_page'),
            'low_stock_threshold' => (string) config('shop.low_stock_threshold'),
            'guest_checkout' => '1',
            'terms_required' => '1',
            'order_prefix' => config('shop.order_prefix'),
            'tax_mode' => config('shop.tax_mode'),
            'default_gateway' => 'manual',
            'stripe_mode' => 'test',
            'max_width' => config('shop.max_width'),
            'rows_per_page' => '25',
        ];

        $existing = Setting::map();
        $written = 0;

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $existing) || $existing[$key] === null) {
                Setting::put($key, (string) $value);
                $written++;
            }
        }

        $this->info("Store settings ready ({$written} defaults written).");
    }

    /**
     * A working shipping setup out of the box: without at least one zone the
     * checkout cannot quote a rate and no order can ever be placed.
     */
    private function seedShipping(): void
    {
        if (ShippingZone::exists()) {
            $this->line('Shipping zones already configured; leaving them alone.');

            return;
        }

        // Atomic: a zone with no rates quotes nothing, so a half-written setup
        // is worse than none. The guard above only sees zones, so partial state
        // would otherwise be skipped on every later run.
        DB::transaction(function (): void {
            $this->writeShippingDefaults();
        });
    }

    private function writeShippingDefaults(): void
    {
        $domestic = ShippingZone::create([
            'name' => 'Domestic (United States)',
            'countries' => ['US'],
            'is_active' => true,
            'position' => 0,
        ]);

        ShippingRate::insert([
            [
                'shipping_zone_id' => $domestic->id,
                'name' => 'Standard (3-5 Business Days)',
                'type' => 'flat',
                'price_cents' => 595,
                'free_above_cents' => 7500,
                'is_active' => true,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shipping_zone_id' => $domestic->id,
                'name' => 'Express (1-2 Business Days)',
                'type' => 'flat',
                'price_cents' => 1495,
                // Every row in a multi-row insert() must carry the same keys:
                // the query builder takes its column list from the first row.
                'free_above_cents' => null,
                'is_active' => true,
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $rest = ShippingZone::create([
            'name' => 'Rest Of World',
            'countries' => ['*'],
            'is_active' => true,
            'position' => 99,
        ]);

        ShippingRate::create([
            'shipping_zone_id' => $rest->id,
            'name' => 'International Standard',
            'type' => 'flat',
            'price_cents' => 2495,
            'is_active' => true,
            'position' => 0,
        ]);

        $this->info('Seeded 2 shipping zones with 3 rates.');
    }

    /** Product images are served from the public disk; it needs its symlink. */
    private function linkStorage(): void
    {
        if (is_link(public_path('storage'))) {
            return;
        }

        $this->callSilently('storage:link');
        $this->info('Linked public/storage for product images.');
    }

    private function issueToken(): void
    {
        $user = User::orderBy('id')->first();

        if (! $user) {
            $this->warn('No staff user yet; finish /setup, then re-run `shop:bootstrap` to issue the API token.');

            return;
        }

        if ($this->option('fresh-token') || ApiToken::count() === 0) {
            [$token, $plain] = ApiToken::issue($user, 'bootstrap-full-access');
            Storage::disk('local')->put('bootstrap-token.txt', $plain."\n");
            $this->info('Full-access API token written to storage/app/private/bootstrap-token.txt');
        } else {
            $this->line('API token already exists; use --fresh-token to issue another.');
        }
    }
}
