<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\SeoUrlService;
use Illuminate\Http\Response;

/**
 * Dynamic robots.txt.
 *
 * Two things it must get right:
 *
 *  - It blocks only pages that are useless or private to crawl (cart, checkout,
 *    account, order confirmations, the admin, the installer). It does NOT block
 *    the faceted catalog URLs or /search, even though those are noindexed:
 *    disallowing a URL stops a crawler fetching it, which means it never reads
 *    the noindex, which means the URL can still surface as a bare listing. The
 *    meta robots tag is what removes them, and it needs the page to be
 *    fetchable to work.
 *
 *  - It honours the site-wide staging switch. A staging copy of a live store
 *    with a real database is the usual way a shop gets its dev site indexed, so
 *    Settings -> SEO -> Discourage Search Engines flips this to a full
 *    Disallow: / and every page to noindex,nofollow at the same time.
 */
class RobotsController extends Controller
{
    public function __invoke(SeoUrlService $urls): Response
    {
        $lines = ['User-agent: *'];

        if (config('seo.site_noindex')) {
            $lines[] = 'Disallow: /';
        } else {
            foreach ([
                '/cart',
                '/checkout',
                '/account',
                '/orders/',
                '/admin',
                '/setup',
                '/magic/',
                '/2fa',
            ] as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap.index');
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
