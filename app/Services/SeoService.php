<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Product;
use App\Support\Seo\SeoData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * The one place a storefront page's <head> is decided.
 *
 * Controllers describe the page ("this is a product page for X"); this resolves
 * title, description, canonical, robots, Open Graph, Twitter and JSON-LD from
 * that plus DB settings. The layout asks for the result. No view hand-rolls a
 * meta tag, and no controller decides an indexing rule.
 *
 * ---------------------------------------------------------------------------
 * Canonical strategy
 * ---------------------------------------------------------------------------
 * E-commerce duplication comes from three places, and each gets a different
 * answer rather than one blunt rule:
 *
 * 1. VARIANTS. ShopMGR picks variants client-side, so a variant is not its own
 *    document; ?variant=N is a deep link into the same page. Those URLs
 *    canonicalise to the bare product URL. One product, one indexed document,
 *    all link equity on it.
 *
 * 2. FILTERED AND SORTED CATALOG VIEWS (?sort, ?min, ?max, ?q). These are
 *    re-orderings and subsets of a set that is already indexed, so they are
 *    near-duplicates with no unique demand. They get robots "noindex, follow"
 *    and a SELF-referencing canonical. The self-canonical matters: a canonical
 *    pointing at the unfiltered page describes different content, which Google
 *    treats as a hint and routinely ignores, leaving the facet indexed anyway.
 *    noindex is the instruction that actually binds; "follow" keeps the crawl
 *    path to the products open. They are deliberately NOT blocked in
 *    robots.txt, because a page a crawler may not fetch is a page whose
 *    noindex it can never read.
 *
 * 3. PAGINATION (?page=N). Each page carries products that appear nowhere else,
 *    so page 2 is not a duplicate of page 1 and must not canonicalise to it —
 *    doing that is the classic way to make deep catalogue pages invisible.
 *    Every page self-canonicalises and stays indexable, and rel=prev/next is
 *    emitted alongside. Google retired prev/next as an indexing signal in 2019
 *    but Bing still consumes it and it costs two link tags.
 *
 * Internal search results (/search) are always noindex,follow: they are
 * unbounded, user-generated URL space, which is the single most common way a
 * shop floods an index with junk.
 */
class SeoService
{
    /** Query parameters that turn a listing into a facet rather than a page. */
    private const FACET_PARAMS = ['sort', 'min', 'max', 'q', 'collection'];

    private ?SeoData $resolved = null;

    public function __construct(
        private SeoUrlService $urls,
        private StructuredDataService $schema,
    ) {}

    /* ------------------------------------------------------------------ *
     * Page descriptions, called from controllers
     * ------------------------------------------------------------------ */

    public function home(): SeoData
    {
        $store = (string) config('shop.store_name');

        return $this->resolved = $this->finish(new SeoData(
            title: $store,
            description: $this->clean(config('seo.default_description') ?: config('shop.store_tagline')),
            canonical: $this->urls->home(),
            robots: $this->robots(),
            og: ['og:type' => 'website'],
            schemas: [$this->schema->organization(), $this->schema->website()],
        ), rawTitle: $store);
    }

    public function product(Product $product): SeoData
    {
        $variant = $product->defaultVariant();
        $image = $this->productImage($product);

        $og = array_filter([
            'og:type' => 'product',
            'og:image' => $image,
            'product:price:amount' => $variant ? $this->decimal($variant->price_cents) : null,
            'product:price:currency' => config('shop.currency', 'USD'),
            'product:availability' => $product->is_in_stock ? 'in stock' : 'out of stock',
            'product:condition' => $this->ogCondition(),
            'product:retailer_item_id' => $variant?->sku,
        ]);

        $trail = $this->productTrail($product);
        $title = $this->autoTitle($product);

        return $this->resolved = $this->finish(new SeoData(
            title: $title,
            description: $this->autoDescription($product),
            // Variant deep links collapse onto the product URL. An explicit
            // canonical_url on the product overrides everything.
            canonical: $this->urls->absolute($product->canonical_url) ?: $this->urls->product($product),
            robots: $this->robots($product->noindex),
            og: $og,
            twitter: $image ? ['twitter:image' => $image] : [],
            schemas: [$this->schema->product($product), $this->schema->breadcrumbs($trail)],
        ), rawTitle: $title, templated: ! $this->hasWrittenTitle($product));
    }

    public function collection(Collection $collection, ?LengthAwarePaginator $products = null): SeoData
    {
        $image = $this->urls->absolute($collection->og_image ?: $collection->image_url)
            ?: $this->urls->absolute(config('seo.default_og_image'));

        $trail = [
            ['name' => 'Home', 'url' => $this->urls->home()],
            ['name' => 'Collections', 'url' => $this->urls->collectionsIndex()],
            ['name' => $collection->name, 'url' => $this->urls->collection($collection)],
        ];

        $title = $this->autoTitle($collection);

        return $this->resolved = $this->finish(new SeoData(
            title: $title,
            description: $this->autoDescription($collection),
            canonical: $this->listingCanonical($this->urls->absolute($collection->canonical_url) ?: $this->urls->collection($collection)),
            robots: $this->listingRobots($collection->noindex),
            og: array_filter(['og:type' => 'website', 'og:image' => $image]),
            twitter: $image ? ['twitter:image' => $image] : [],
            schemas: array_filter([
                $products
                    ? $this->schema->itemList($products->items(), $collection->name, $this->urls->collection($collection))
                    : $this->schema->collection($collection),
                $this->schema->breadcrumbs($trail),
            ]),
            links: $this->paginationLinks($products),
        ), rawTitle: $title, templated: ! $this->hasWrittenTitle($collection));
    }

    public function catalog(?LengthAwarePaginator $products = null): SeoData
    {
        $trail = [
            ['name' => 'Home', 'url' => $this->urls->home()],
            ['name' => 'All Products', 'url' => $this->urls->catalog()],
        ];

        return $this->resolved = $this->finish(new SeoData(
            title: 'All Products',
            description: $this->clean('Browse every product available from '.config('shop.store_name').'.'),
            canonical: $this->listingCanonical($this->urls->catalog()),
            robots: $this->listingRobots(),
            og: ['og:type' => 'website'],
            schemas: array_filter([
                $products ? $this->schema->itemList($products->items(), 'All Products', $this->urls->catalog()) : null,
                $this->schema->breadcrumbs($trail),
            ]),
            links: $this->paginationLinks($products),
        ), rawTitle: 'All Products');
    }

    public function collectionsIndex(): SeoData
    {
        return $this->resolved = $this->finish(new SeoData(
            title: 'Collections',
            description: $this->clean('Browse '.config('shop.store_name').' by collection.'),
            canonical: $this->urls->collectionsIndex(),
            robots: $this->robots(),
            og: ['og:type' => 'website'],
        ), rawTitle: 'Collections');
    }

    /** Internal search results: never indexed, always crawlable onward. */
    public function search(?string $term): SeoData
    {
        $title = $term ? 'Results For "'.$term.'"' : 'Search';

        return $this->resolved = $this->finish(new SeoData(
            title: $title,
            description: $this->clean('Search '.config('shop.store_name').'.'),
            canonical: $this->urls->current(withQuery: true),
            robots: $this->robots(force: true),
        ), rawTitle: $title);
    }

    /**
     * Cart, checkout, account and confirmation pages. Transactional, private,
     * or both: noindex,nofollow and no structured data.
     */
    public function transactional(string $title): SeoData
    {
        return $this->resolved = $this->finish(new SeoData(
            title: $title,
            description: '',
            canonical: null,
            robots: 'noindex, nofollow',
        ), rawTitle: $title);
    }

    /**
     * What the layout renders. A storefront page whose controller said nothing
     * still gets a complete, sane head rather than a bare title.
     */
    public function resolve(?string $fallbackTitle = null): SeoData
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        // Cart, checkout, order confirmation and customer account pages are
        // noindexed here rather than in their controllers, so the payment and
        // checkout code stays untouched by the SEO layer.
        if ($this->isPrivateRoute()) {
            return $this->transactional($fallbackTitle ?: (string) config('shop.store_name'));
        }

        return $this->resolved = $this->finish(new SeoData(
            title: $fallbackTitle ?: (string) config('shop.store_name'),
            description: $this->clean(config('seo.default_description') ?: config('shop.store_tagline')),
            canonical: $this->urls->current(),
            robots: $this->robots(),
            og: ['og:type' => 'website'],
        ), rawTitle: $fallbackTitle ?: (string) config('shop.store_name'));
    }

    /**
     * Home > (first collection) > Product. Mirrors the visible breadcrumb on
     * the product page, which is the point: the markup has to describe what a
     * shopper can actually see.
     *
     * @return array<int, array{name: string, url: string}>
     */
    private function productTrail(Product $product): array
    {
        $trail = [['name' => 'Home', 'url' => $this->urls->home()]];

        if ($collection = $product->collections->first()) {
            $trail[] = ['name' => $collection->name, 'url' => $this->urls->collection($collection)];
        }

        $trail[] = ['name' => $product->name, 'url' => $this->urls->product($product)];

        return $trail;
    }

    /** Routes that must never be indexed, whatever else is configured. */
    private function isPrivateRoute(): bool
    {
        return request()->routeIs(
            'shop.cart',
            'shop.cart.*',
            'shop.checkout',
            'shop.checkout.*',
            'shop.account',
            'shop.account.*',
        );
    }

    /* ------------------------------------------------------------------ *
     * Auto-generated fallbacks from real catalog data
     * ------------------------------------------------------------------ */

    /** True when a human typed the title, rather than it being derived. */
    private function hasWrittenTitle(Product|Collection $entity): bool
    {
        return filled($entity->meta_title) || filled($entity->seo_title);
    }

    /**
     * meta_title -> legacy seo_title -> the entity name. Never a blank title
     * and never a template-looking placeholder.
     */
    public function autoTitle(Product|Collection $entity): string
    {
        return $this->clean(
            $entity->meta_title
                ?: $entity->seo_title
                ?: $entity->name
        );
    }

    /**
     * meta_description -> legacy seo_description -> excerpt -> the first
     * sentences of the description -> a sentence built from the real facts on
     * the record (vendor, type, price). The last rung exists because a blank
     * description hands the snippet to the crawler's guess, and for a product
     * the guess is usually the navigation.
     */
    public function autoDescription(Product|Collection $entity): string
    {
        $candidates = [
            $entity->meta_description,
            $entity->seo_description,
            $entity instanceof Product ? $entity->excerpt : null,
            $entity->description,
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return $this->clean($candidate, (int) config('seo.description_max', 160));
            }
        }

        if ($entity instanceof Product) {
            return $this->clean($this->productSentence($entity), (int) config('seo.description_max', 160));
        }

        return $this->clean(
            'Shop the '.$entity->name.' collection at '.config('shop.store_name').'.',
            (int) config('seo.description_max', 160)
        );
    }

    /** A description assembled from facts that are actually on the product. */
    private function productSentence(Product $product): string
    {
        $parts = [$product->name];

        if ($product->vendor) {
            $parts[] = 'by '.$product->vendor;
        }

        $sentence = implode(' ', $parts).'.';

        if ($product->variants->isNotEmpty()) {
            $sentence .= $product->has_price_range
                ? ' From '.$product->price_from_formatted.'.'
                : ' '.$product->price_from_formatted.'.';
        }

        return $sentence.' Available now at '.config('shop.store_name').'.';
    }

    /* ------------------------------------------------------------------ *
     * Robots and canonicals
     * ------------------------------------------------------------------ */

    /** Site-wide staging switch wins over everything. */
    private function robots(bool $entityNoindex = false, bool $force = false): string
    {
        if (config('seo.site_noindex')) {
            return 'noindex, nofollow';
        }

        return ($entityNoindex || $force) ? 'noindex, follow' : 'index, follow';
    }

    /** A listing is noindexed when it is a facet, a sort, or a search slice. */
    private function listingRobots(bool $entityNoindex = false): string
    {
        return $this->robots($entityNoindex, force: $this->isFaceted());
    }

    /**
     * Self-referencing on facets (so the noindex is the binding instruction),
     * self-referencing on pagination (page 2 is not page 1), and the bare URL
     * otherwise.
     */
    private function listingCanonical(string $baseUrl): string
    {
        $query = [];

        if ($this->isFaceted()) {
            $query = array_filter(
                request()->only(array_merge(self::FACET_PARAMS, ['page'])),
                fn ($value) => $value !== null && $value !== ''
            );
        } elseif (($page = (int) request()->query('page', 1)) > 1) {
            $query = ['page' => $page];
        }

        return $query ? $baseUrl.'?'.http_build_query($query) : $baseUrl;
    }

    private function isFaceted(): bool
    {
        foreach (self::FACET_PARAMS as $param) {
            if (filled(request()->query($param))) {
                return true;
            }
        }

        return false;
    }

    /** rel=prev / rel=next for a paginated listing. */
    private function paginationLinks(?LengthAwarePaginator $products): array
    {
        if (! $products || $products->lastPage() <= 1) {
            return [];
        }

        $links = [];

        if ($products->currentPage() > 1) {
            // Page 1 canonicalises to the bare URL, so rel=prev must point at
            // the bare URL too rather than at ?page=1. Two spellings of the
            // first page is exactly the duplication this layer exists to stop.
            $links['prev'] = $products->currentPage() === 2
                ? $this->urls->current()
                : $this->urls->absolute($products->previousPageUrl());
        }
        if ($products->currentPage() < $products->lastPage()) {
            $links['next'] = $this->urls->absolute($products->nextPageUrl());
        }

        return array_filter($links);
    }

    /* ------------------------------------------------------------------ *
     * Assembly
     * ------------------------------------------------------------------ */

    /**
     * Fill in everything common to every page: the title template, the OG and
     * Twitter blocks that mirror the title/description/canonical, the default
     * image, and the search console tokens.
     */
    private function finish(SeoData $data, string $rawTitle, bool $templated = true): SeoData
    {
        // A hand-written meta title is the whole title. Appending the store
        // name to it produces "Waxed Cotton Overnight Bag: ShopMGR: ShopMGR"
        // the moment a merchant writes the store name into the field, and it
        // takes the length control away from the person who chose the words.
        $data->title = $templated ? $this->applyTemplate($rawTitle) : $rawTitle;

        $image = $data->og['og:image'] ?? $this->urls->absolute(config('seo.default_og_image'));

        $data->og = array_filter(array_merge([
            'og:site_name' => config('shop.store_name'),
            'og:title' => $rawTitle,
            'og:description' => $data->description,
            'og:url' => $data->canonical ?: $this->urls->current(),
            'og:locale' => str_replace('-', '_', app()->getLocale() === 'en' ? 'en_US' : app()->getLocale()),
            'og:image' => $image,
        ], $data->og));

        $data->twitter = array_filter(array_merge([
            'twitter:card' => $image
                ? (string) config('seo.twitter_card', 'summary_large_image')
                : 'summary',
            'twitter:title' => $rawTitle,
            'twitter:description' => $data->description,
            'twitter:site' => config('seo.twitter_site'),
            'twitter:image' => $image,
        ], $data->twitter));

        $data->verifications = array_filter([
            'google-site-verification' => config('seo.google_verification'),
            'msvalidate.01' => config('seo.bing_verification'),
        ]);

        $data->schemas = array_values(array_filter($data->schemas));

        return $data;
    }

    /** "{title}: {store}", with the store name not repeated on the home page. */
    private function applyTemplate(string $rawTitle): string
    {
        $store = (string) config('shop.store_name');
        $template = (string) config('seo.title_template', '{title}: {store}');

        if ($rawTitle === $store || blank($store)) {
            return $rawTitle ?: $store;
        }

        return trim(str_replace(['{title}', '{store}'], [$rawTitle, $store], $template));
    }

    private function productImage(Product $product): ?string
    {
        return $this->urls->absolute($product->og_image)
            ?: $this->urls->absolute($product->primaryImage()?->url)
            ?: $this->urls->absolute(config('seo.default_og_image'));
    }

    private function ogCondition(): string
    {
        return match (config('seo.item_condition', 'NewCondition')) {
            'UsedCondition' => 'used',
            'RefurbishedCondition' => 'refurbished',
            default => 'new',
        };
    }

    private function decimal(?int $cents): string
    {
        $decimals = (int) config('shop.currency_decimals', 2);

        return number_format(((int) $cents) / (10 ** $decimals), $decimals, '.', '');
    }

    /** Collapse whitespace, strip markup, and trim to a sensible length. */
    private function clean(?string $value, ?int $limit = null): string
    {
        if (blank($value)) {
            return '';
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return $limit ? Str::limit($text, $limit, '') : $text;
    }
}
