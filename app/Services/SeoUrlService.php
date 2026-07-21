<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Product;

/**
 * Absolute, canonical-safe URLs.
 *
 * Canonicals, sitemaps and JSON-LD must all agree on one spelling of a URL, so
 * every one of them goes through here rather than calling route() ad hoc. The
 * rules: always absolute, always the APP_URL host (a store reached on an IP or
 * a preview hostname still points search engines at the real domain), and never
 * a trailing slash except on the home page.
 */
class SeoUrlService
{
    public function home(): string
    {
        return rtrim($this->base(), '/').'/';
    }

    public function product(Product $product): string
    {
        return $this->normalise(route('shop.product', $product->slug));
    }

    public function collection(Collection $collection): string
    {
        return $this->normalise(route('shop.collection', $collection->slug));
    }

    public function catalog(): string
    {
        return $this->normalise(route('shop.catalog'));
    }

    public function collectionsIndex(): string
    {
        return $this->normalise(route('shop.collections'));
    }

    /** The current request URL, host-normalised, with its query string kept. */
    public function current(bool $withQuery = false): string
    {
        $url = $withQuery ? request()->fullUrl() : request()->url();

        return $this->normalise($url);
    }

    /** The current path with a specific set of query parameters. */
    public function currentWith(array $query): string
    {
        $base = $this->normalise(request()->url());

        return $query ? $base.'?'.http_build_query($query) : $base;
    }

    /** Turn a possibly-relative asset path into an absolute URL. */
    public function absolute(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $this->normalise($url);
        }

        return rtrim($this->base(), '/').'/'.ltrim($url, '/');
    }

    private function base(): string
    {
        return (string) (config('app.url') ?: request()->getSchemeAndHttpHost());
    }

    /**
     * Force the configured scheme+host onto a URL and strip a trailing slash.
     * Keeps http/https and www/non-www duplicates from ever being emitted.
     */
    private function normalise(string $url): string
    {
        $base = rtrim($this->base(), '/');
        $parts = parse_url($url);

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        if ($path !== '/' ) {
            $path = rtrim($path, '/');
        }

        return $base.$path.$query;
    }
}
