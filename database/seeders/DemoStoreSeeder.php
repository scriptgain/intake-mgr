<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A believable demo catalog, so a fresh install has something to click through.
 *
 * Idempotent: keyed on slugs/codes/emails, so re-running it will not duplicate.
 * Safe to run on a demo host; pointless (but harmless) on a real store.
 */
class DemoStoreSeeder extends Seeder
{
    public function run(): void
    {
        $collections = $this->collections();
        $this->products($collections);
        $this->discounts();
        $this->customers();
    }

    private function collections(): array
    {
        $rows = [
            ['Everyday Carry', 'Bags, wallets, and the small things you reach for daily.', 0],
            ['Home & Kitchen', 'Considered objects for the places you spend the most time.', 1],
            ['Desk & Studio', 'Tools for focused work.', 2],
        ];

        $out = [];
        foreach ($rows as [$name, $description, $position]) {
            $out[$name] = Collection::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description, 'position' => $position, 'is_active' => true]
            );
        }

        return $out;
    }

    private function products(array $collections): void
    {
        // [name, collection, excerpt, price cents, compare-at, options, stock]
        $rows = [
            ['Canvas Weekender Bag', 'Everyday Carry', 'Waxed canvas, leather trim, and a lining that survives airports.', 18900, 22900, ['Color' => ['Charcoal', 'Olive', 'Sand']], 24],
            ['Leather Card Wallet', 'Everyday Carry', 'Four pockets, no bulk. Vegetable-tanned and built to patina.', 5400, null, ['Color' => ['Black', 'Tan']], 60],
            ['Insulated Travel Flask', 'Everyday Carry', 'Holds temperature for eighteen hours. Fits a car cupholder.', 3800, 4500, ['Size' => ['12 oz', '20 oz']], 45],
            ['Stoneware Mug Set', 'Home & Kitchen', 'Four hand-glazed mugs. No two come out quite the same.', 6800, null, [], 30],
            ['Cast Iron Skillet', 'Home & Kitchen', 'Pre-seasoned, 10-inch, and effectively immortal.', 8900, null, ['Size' => ['10 in', '12 in']], 18],
            ['Linen Tea Towel Trio', 'Home & Kitchen', 'Stonewashed European flax that gets softer every wash.', 3200, 3900, ['Color' => ['Natural', 'Indigo']], 52],
            ['Solid Brass Desk Lamp', 'Desk & Studio', 'Weighted base, articulating arm, warm dimmable bulb included.', 24500, null, [], 9],
            ['Hardcover Dot Grid Notebook', 'Desk & Studio', '192 pages of 100gsm paper that does not ghost.', 2400, null, ['Color' => ['Black', 'Forest', 'Oxblood']], 120],
            ['Machined Pen', 'Desk & Studio', 'Solid aluminium, knurled grip, standard refill.', 7200, 8500, ['Color' => ['Silver', 'Graphite']], 33],
        ];

        foreach ($rows as $i => [$name, $collectionName, $excerpt, $price, $compareAt, $options, $stock]) {
            $product = Product::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'excerpt' => $excerpt,
                    'description' => $excerpt.' '.'Made in small batches and backed by a two-year guarantee. '
                        .'Ships free on orders over seventy-five dollars.',
                    'status' => 'active',
                    'vendor' => 'ShopMGR Demo Goods',
                    'is_featured' => $i < 4,
                    'position' => $i,
                    'requires_shipping' => true,
                ]
            );

            if ($collection = $collections[$collectionName] ?? null) {
                $product->collections()->syncWithoutDetaching([$collection->id]);
            }

            if ($product->variants()->exists()) {
                continue;
            }

            if (! $options) {
                $product->variants()->create([
                    'sku' => $this->sku($name),
                    'price_cents' => $price,
                    'compare_at_price_cents' => $compareAt,
                    'cost_cents' => (int) round($price * 0.42),
                    'inventory_qty' => $stock,
                    'weight_grams' => 600,
                    'is_default' => true,
                    'position' => 0,
                ]);

                continue;
            }

            $axis = array_key_first($options);
            foreach (array_values($options[$axis]) as $position => $value) {
                $product->variants()->create([
                    'option1_name' => $axis,
                    'option1_value' => $value,
                    'sku' => $this->sku($name).'-'.strtoupper(substr(Str::slug($value), 0, 3)),
                    'price_cents' => $price + ($position * 500),
                    'compare_at_price_cents' => $compareAt ? $compareAt + ($position * 500) : null,
                    'cost_cents' => (int) round($price * 0.42),
                    // Vary stock so the low-stock and out-of-stock states are
                    // both visible on a demo instance.
                    'inventory_qty' => max(0, $stock - ($position * 9)),
                    'weight_grams' => 600,
                    'is_default' => $position === 0,
                    'position' => $position,
                ]);
            }
        }
    }

    private function discounts(): void
    {
        Discount::firstOrCreate(['code' => 'WELCOME10'], [
            'title' => 'Welcome — 10% Off',
            'type' => 'percentage',
            'value' => 1000, // basis points
            'applies_to' => 'all',
            'once_per_customer' => true,
            'is_active' => true,
        ]);

        Discount::firstOrCreate(['code' => 'FREESHIP'], [
            'title' => 'Free Shipping Over $50',
            'type' => 'free_shipping',
            'value' => 0,
            'applies_to' => 'all',
            'min_subtotal_cents' => 5000,
            'is_active' => true,
        ]);
    }

    private function customers(): void
    {
        foreach ([
            ['Dana', 'Whitfield', 'dana@example.com'],
            ['Marcus', 'Ellery', 'marcus@example.com'],
            ['Priya', 'Raman', 'priya@example.com'],
        ] as [$first, $last, $email]) {
            Customer::firstOrCreate(['email' => $email], [
                'first_name' => $first,
                'last_name' => $last,
                'accepts_marketing' => true,
            ]);
        }
    }

    private function sku(string $name): string
    {
        return 'SM-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 6));
    }
}
