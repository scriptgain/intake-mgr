<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * schema.org JSON-LD builders.
 *
 * The Product node is the highest-value output in the whole SEO layer: it is
 * what puts a price, a currency, and an availability state into a shopping
 * result. Every figure it emits is read from the database (product_variants
 * .price_cents, .inventory_qty) and converted here. Nothing is invented.
 *
 * AggregateRating and Review are conditional on real review data existing.
 * ShopMGR ships no reviews table, so on a stock install those nodes are simply
 * absent, which is correct: fabricated ratings are a manual-action risk, not a
 * missing feature.
 */
class StructuredDataService
{
    public function __construct(private SeoUrlService $urls) {}

    /* ------------------------------------------------------------------ *
     * Product
     * ------------------------------------------------------------------ */

    /**
     * A full Product node. $product is expected to arrive with variants and
     * images already loaded (CatalogController::product does this).
     */
    public function product(Product $product): array
    {
        $node = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $this->urls->product($product).'#product',
            'name' => $product->name,
            'description' => $this->plainText($product->description ?: $product->excerpt),
            'url' => $this->urls->product($product),
            'image' => $this->images($product),
            'sku' => $product->defaultVariant()?->sku,
            'mpn' => $product->defaultVariant()?->barcode,
            'category' => $product->collections->first()?->name ?? $product->product_type,
            'brand' => $product->vendor ? ['@type' => 'Brand', 'name' => $product->vendor] : null,
            'offers' => $this->offers($product),
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');

        // Only present when the store actually holds reviews.
        if ($rating = $this->aggregateRating($product)) {
            $node['aggregateRating'] = $rating;
        }
        if ($reviews = $this->reviews($product)) {
            $node['review'] = $reviews;
        }

        return $node;
    }

    /**
     * One Offer per variant, wrapped in an AggregateOffer when the product has
     * more than one. A single-variant product gets a bare Offer, which is what
     * Google's examples show and what its price extractor is happiest with.
     */
    private function offers(Product $product): ?array
    {
        $variants = $product->variants;

        if ($variants->isEmpty()) {
            return null;
        }

        $offers = $variants->map(fn (ProductVariant $variant) => $this->offer($product, $variant))->values()->all();

        if (count($offers) === 1) {
            return $offers[0];
        }

        $prices = $variants->pluck('price_cents')->all();

        return [
            '@type' => 'AggregateOffer',
            'priceCurrency' => config('shop.currency', 'USD'),
            'lowPrice' => $this->decimal(min($prices)),
            'highPrice' => $this->decimal(max($prices)),
            'offerCount' => count($offers),
            'availability' => $this->availability($product->is_in_stock, $product->variants->contains('track_inventory', true)),
            'offers' => $offers,
        ];
    }

    private function offer(Product $product, ProductVariant $variant): array
    {
        return array_filter([
            '@type' => 'Offer',
            'url' => $this->urls->product($product).'?variant='.$variant->id,
            'price' => $this->decimal($variant->price_cents),
            'priceCurrency' => config('shop.currency', 'USD'),
            'availability' => $this->availability($variant->is_in_stock, $variant->track_inventory),
            'itemCondition' => 'https://schema.org/'.config('seo.item_condition', 'NewCondition'),
            'sku' => $variant->sku,
            'name' => $variant->name !== 'Default' ? $variant->name : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * InStock / OutOfStock / BackOrder. BackOrder is only ever claimed when the
     * store is genuinely configured to accept orders past zero stock.
     */
    private function availability(bool $inStock, bool $tracked): string
    {
        if ($inStock) {
            return $tracked && config('shop.allow_backorder')
                ? 'https://schema.org/BackOrder'
                : 'https://schema.org/InStock';
        }

        return 'https://schema.org/OutOfStock';
    }

    /** Integer cents to the plain decimal string schema.org expects. */
    private function decimal(?int $cents): string
    {
        $decimals = (int) config('shop.currency_decimals', 2);

        return number_format(((int) $cents) / (10 ** $decimals), $decimals, '.', '');
    }

    /** @return array<int, string> */
    private function images(Product $product): array
    {
        return $product->images
            ->map(fn ($image) => $this->urls->absolute($image->url))
            ->filter()
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ *
     * Reviews — only if the store has them
     * ------------------------------------------------------------------ */

    /**
     * ShopMGR has no reviews table today. This stays here (rather than being
     * hard-coded to null) so that the day a reviews table lands the rich result
     * turns itself on, and so the intent is unambiguous: no data, no node.
     */
    private function hasReviewData(): bool
    {
        return Cache::remember('seo.reviews_table', 300, function () {
            try {
                return Schema::hasTable('reviews')
                    && Schema::hasColumn('reviews', 'rating')
                    && Schema::hasColumn('reviews', 'product_id');
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    private function aggregateRating(Product $product): ?array
    {
        if (! $this->hasReviewData()) {
            return null;
        }

        $row = \Illuminate\Support\Facades\DB::table('reviews')
            ->where('product_id', $product->id)
            ->when(
                Schema::hasColumn('reviews', 'is_approved'),
                fn ($q) => $q->where('is_approved', true)
            )
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        if (! $row || ! $row->total) {
            return null;
        }

        return [
            '@type' => 'AggregateRating',
            'ratingValue' => round((float) $row->average, 1),
            'reviewCount' => (int) $row->total,
        ];
    }

    private function reviews(Product $product): array
    {
        if (! $this->hasReviewData()) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('reviews')
            ->where('product_id', $product->id)
            ->when(
                Schema::hasColumn('reviews', 'is_approved'),
                fn ($q) => $q->where('is_approved', true)
            )
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($review) => array_filter([
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (float) $review->rating,
                    'bestRating' => 5,
                ],
                'author' => ['@type' => 'Person', 'name' => $review->author_name ?? 'Customer'],
                'reviewBody' => $review->body ?? null,
                'datePublished' => isset($review->created_at)
                    ? (string) Str::of((string) $review->created_at)->before(' ')
                    : null,
            ], fn ($value) => $value !== null))
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ *
     * Site-level nodes
     * ------------------------------------------------------------------ */

    public function organization(): array
    {
        $sameAs = array_values(array_filter(array_map(
            'trim',
            preg_split('/\R/', (string) config('seo.organization_sameas')) ?: []
        )));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $this->urls->home().'#organization',
            'name' => config('seo.organization_name') ?: config('shop.store_name'),
            'url' => $this->urls->home(),
            'logo' => config('seo.organization_logo') ?: null,
            'email' => config('shop.store_email') ?: null,
            'telephone' => config('shop.store_phone') ?: null,
            'sameAs' => $sameAs ?: null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /** WebSite with the SearchAction that wires the site search into results. */
    public function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $this->urls->home().'#website',
            'name' => config('shop.store_name'),
            'url' => $this->urls->home(),
            'publisher' => ['@id' => $this->urls->home().'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('shop.search').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $trail
     */
    public function breadcrumbs(array $trail): ?array
    {
        if (count($trail) < 2) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(fn (int $index, array $crumb) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ], array_keys($trail), $trail)),
        ];
    }

    /** A collection or catalog page, with its visible products as an ItemList. */
    public function itemList(iterable $products, string $name, string $url): array
    {
        $items = [];
        $position = 1;

        foreach ($products as $product) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'url' => $this->urls->product($product),
                'name' => $product->name,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $url,
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => count($items),
                'itemListElement' => $items,
            ],
        ];
    }

    public function collection(Collection $collection): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $collection->name,
            'url' => $this->urls->collection($collection),
            'description' => $this->plainText($collection->description),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /* ------------------------------------------------------------------ */

    private function plainText(?string $value, int $limit = 5000): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? ''), $limit, '');
    }
}
