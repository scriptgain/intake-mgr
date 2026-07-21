<?php

namespace App\View\Components;

use App\Services\ThemeService;
use Illuminate\View\Component;

/**
 * The browser-tab icon links.
 *
 * A theme may carry its own favicon upload; if it does not, the product falls
 * back to FaviconController, which draws the brand glyph in the accent colour.
 * Deciding between the two is logic, so it happens here and the template just
 * prints tags.
 */
class FaviconLinks extends Component
{
    public ?string $custom;

    public function __construct(ThemeService $themes)
    {
        $this->custom = $themes->active()?->faviconUrl();
    }

    public function render()
    {
        return view('components.favicon-links');
    }
}
