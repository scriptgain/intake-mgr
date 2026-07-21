<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\SitemapService;
use Illuminate\Http\Response;

/**
 * sitemap.xml and its sections.
 *
 * The XML is assembled here with XMLWriter rather than in a Blade view: a
 * sitemap is a machine document, the escaping rules are not HTML's, and a
 * template whose first line has to be a literal <?xml declaration is exactly
 * the kind of thing that turns into a 500 on a deploy.
 */
class SitemapController extends Controller
{
    private const SITEMAP_NS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    private const IMAGE_NS = 'http://www.google.com/schemas/sitemap-image/1.1';

    public function __construct(private SitemapService $sitemap) {}

    public function index(): Response
    {
        $writer = $this->writer();
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', self::SITEMAP_NS);

        foreach ($this->sitemap->index() as $section) {
            $writer->startElement('sitemap');
            $writer->writeElement('loc', $section['loc']);
            $writer->writeElement('lastmod', $section['lastmod']);
            $writer->endElement();
        }

        $writer->endElement();

        return $this->respond($writer->outputMemory());
    }

    public function pages(): Response
    {
        return $this->urlset($this->sitemap->pages());
    }

    public function products(int $page = 1): Response
    {
        return $this->urlset($this->sitemap->products(max(1, $page)));
    }

    public function collections(int $page = 1): Response
    {
        return $this->urlset($this->sitemap->collections(max(1, $page)));
    }

    /* ------------------------------------------------------------------ */

    private function urlset(array $urls): Response
    {
        $writer = $this->writer();
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', self::SITEMAP_NS);
        $writer->writeAttribute('xmlns:image', self::IMAGE_NS);

        foreach ($urls as $url) {
            $writer->startElement('url');
            $writer->writeElement('loc', $url['loc']);

            if (! empty($url['lastmod'])) {
                $writer->writeElement('lastmod', $url['lastmod']);
            }
            if (! empty($url['changefreq'])) {
                $writer->writeElement('changefreq', $url['changefreq']);
            }
            if (! empty($url['priority'])) {
                $writer->writeElement('priority', $url['priority']);
            }
            if (! empty($url['image'])) {
                $writer->startElement('image:image');
                $writer->writeElement('image:loc', $url['image']);
                $writer->endElement();
            }

            $writer->endElement();
        }

        $writer->endElement();

        return $this->respond($writer->outputMemory());
    }

    private function writer(): \XMLWriter
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');

        return $writer;
    }

    private function respond(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
