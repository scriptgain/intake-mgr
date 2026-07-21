<?php

namespace App\View\Components;

use App\Models\Collection;
use App\Models\Product;
use App\Services\SeoService;
use Illuminate\View\Component;

/**
 * The SEO editor shared by the product and collection edit screens.
 *
 * Collapsed by default: a merchant editing stock or price should not have to
 * scroll past six fields they are not touching. Everything the panel needs to
 * render (the auto-generated fallbacks, the live URL, the counter limits) is
 * resolved here so the template stays markup.
 */
class SeoPanel extends Component
{
    public string $autoTitle;

    public string $autoDescription;

    public string $liveUrl;

    public array $limits;

    public bool $hasOverrides;

    public function __construct(
        public Product|Collection $entity,
        SeoService $seo,
    ) {
        $this->autoTitle = $seo->autoTitle($entity);
        $this->autoDescription = $seo->autoDescription($entity);
        $this->liveUrl = $this->resolveUrl();

        $this->limits = [
            'titleMin' => (int) config('seo.title_min', 25),
            'titleMax' => (int) config('seo.title_max', 60),
            'descriptionMin' => (int) config('seo.description_min', 70),
            'descriptionMax' => (int) config('seo.description_max', 160),
        ];

        // Drives the "Customised" chip on the collapsed header, so a merchant
        // can see at a glance that this record has hand-written SEO without
        // opening the panel.
        $this->hasOverrides = filled($entity->meta_title)
            || filled($entity->meta_description)
            || filled($entity->og_image)
            || filled($entity->canonical_url)
            || (bool) $entity->noindex;
    }

    private function resolveUrl(): string
    {
        if (! $this->entity->exists || blank($this->entity->slug)) {
            return rtrim((string) config('app.url'), '/').'/';
        }

        return $this->entity instanceof Product
            ? route('shop.product', $this->entity->slug)
            : route('shop.collection', $this->entity->slug);
    }

    public function render()
    {
        return view('components.seo-panel');
    }
}
