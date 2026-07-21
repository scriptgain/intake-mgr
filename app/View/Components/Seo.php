<?php

namespace App\View\Components;

use App\Services\SeoService;
use App\Support\Seo\SeoData;
use Illuminate\View\Component;

/**
 * The storefront <head>'s SEO block.
 *
 * The template that renders this is a flat list of tags with no conditionals
 * beyond "is this string present": every decision has already been made by
 * SeoService, and the JSON-LD has already been encoded here.
 */
class Seo extends Component
{
    public SeoData $seo;

    public ?string $jsonLd;

    public function __construct(SeoService $seo, public ?string $fallbackTitle = null)
    {
        $this->seo = $seo->resolve($this->fallbackTitle);
        $this->jsonLd = $this->seo->jsonLd();
    }

    public function render()
    {
        return view('components.seo');
    }
}
