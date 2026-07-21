<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Sitemap generation.
 *
 * Always a sitemap index, even for a nine-product store. A shop is the kind of
 * site that goes from 9 SKUs to 9,000 without anybody revisiting the SEO code,
 * and the index costs one extra request now versus a rewrite later.
 *
 * What is deliberately excluded:
 *   - anything with noindex set on the record;
 *   - non-active products and inactive collections;
 *   - out-of-stock products, when the store hides them from listings (a URL
 *     the storefront will not show is not a URL to advertise), or when the
 *     merchant has switched the sitemap policy off at Settings -> SEO.
 * Cart, checkout, account and search never appear: they are noindex.
 */
class SitemapService
{
    public function __construct(private SeoUrlService $urls) {}

    /** Sections of the index, each already resolved to its own sitemap URL. */
    public function index(): array
    {
        $sections = [
            ['loc' => route('sitemap.pages'), 'lastmod' => $this->latest([Product::query(), Collection::query()])],
        ];

        foreach (range(1, max(1, (int) ceil($this->productQuery()->count() / $this->chunk()))) as $page) {
            $sections[] = [
                'loc' => route('sitemap.products', ['page' => $page]),
                'lastmod' => $this->latest([$this->productQuery()]),
            ];
        }

        foreach (range(1, max(1, (int) ceil($this->collectionQuery()->count() / $this->chunk()))) as $page) {
            $sections[] = [
                'loc' => route('sitemap.collections', ['page' => $page]),
                'lastmod' => $this->latest([$this->collectionQuery()]),
            ];
        }

        return $sections;
    }

    /** Content pages: the ones the storefront actually serves and indexes. */
    public function pages(): array
    {
        return [
            [
                'loc' => $this->urls->home(),
                'lastmod' => $this->latest([$this->productQuery()]),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => $this->urls->catalog(),
                'lastmod' => $this->latest([$this->productQuery()]),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ],
            [
                'loc' => $this->urls->collectionsIndex(),
                'lastmod' => $this->latest([$this->collectionQuery()]),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
        ];
    }

    public function products(int $page): array
    {
        return $this->productQuery()
            ->orderBy('id')
            ->forPage($page, $this->chunk())
            ->get()
            ->map(fn (Product $product) => [
                'loc' => $this->urls->product($product),
                'lastmod' => $this->stamp($product->updated_at),
                'changefreq' => 'weekly',
                'priority' => $product->is_featured ? '0.9' : '0.8',
                'image' => $this->urls->absolute($product->primaryImage()?->url),
            ])
            ->all();
    }

    public function collections(int $page): array
    {
        return $this->collectionQuery()
            ->orderBy('id')
            ->forPage($page, $this->chunk())
            ->get()
            ->map(fn (Collection $collection) => [
                'loc' => $this->urls->collection($collection),
                'lastmod' => $this->stamp($collection->updated_at),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ])
            ->all();
    }

    /* ------------------------------------------------------------------ */

    /** Products eligible for the index: active, indexable, and buyable. */
    public function productQuery(): Builder
    {
        $query = Product::query()
            ->where('status', 'active')
            ->where(fn (Builder $q) => $q->where('noindex', false)->orWhereNull('noindex'))
            ->with(['images']);

        // The storefront's own hide policy always wins: those pages are not
        // reachable by browsing, so they do not belong in a sitemap.
        $includeOutOfStock = config('seo.sitemap_include_out_of_stock', true)
            && ! config('shop.hide_out_of_stock');

        if (! $includeOutOfStock) {
            $query->whereHas('variants', function (Builder $v) {
                $v->where('track_inventory', false)->orWhere('inventory_qty', '>', 0);
            });
        }

        return $query;
    }

    public function collectionQuery(): Builder
    {
        return Collection::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->where('noindex', false)->orWhereNull('noindex'));
    }

    public function chunk(): int
    {
        // Hard-capped well below the 50,000-URL protocol limit.
        return max(100, min(45000, (int) config('seo.sitemap_chunk', 2000)));
    }

    /** How many sitemap files the index will list. */
    public function sectionCount(): int
    {
        return count($this->index());
    }

    /** The newest updated_at across the given queries, as a W3C stamp. */
    private function latest(array $queries): string
    {
        $newest = null;

        foreach ($queries as $query) {
            $stamp = (clone $query)->max('updated_at');
            if ($stamp && (! $newest || $stamp > $newest)) {
                $newest = $stamp;
            }
        }

        return $this->stamp($newest ? Carbon::parse($newest) : null);
    }

    private function stamp(mixed $value): string
    {
        return $value instanceof \DateTimeInterface
            ? Carbon::instance($value)->toAtomString()
            : Carbon::now()->toAtomString();
    }
}
