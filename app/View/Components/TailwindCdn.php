<?php

namespace App\View\Components;

use App\Services\ThemeService;
use Illuminate\View\Component;

/**
 * Builds the CSS handed to the in-browser Tailwind v4 compiler.
 *
 * Three layers, in cascade order:
 *
 *   1. resources/css/app.css, minus the build-only @import/@source lines the
 *      browser build does not use. Read from the file rather than pasted here
 *      so @theme/@apply never appear in Blade source, where Blade would try to
 *      read them as directives.
 *   2. The legacy config('brand.accent') ramp, for installs that set an accent
 *      before the Theme Manager existed and have no theme rows yet.
 *   3. The active theme's tokens. Last, so a theme always wins.
 *
 * The work lives in this class, not in the template, because the template's job
 * is markup (house rule: no PHP logic in views).
 */
class TailwindCdn extends Component
{
    public string $tokens;

    /**
     * @param  bool  $applyTheme  Whether the merchant's active THEME (colours,
     *   radius, spacing, type scale) is applied. The storefront passes true;
     *   the merchant admin passes false, so a merchant theming their shopfront
     *   does not also restyle the tools they work in all day. Both layouts
     *   still get the shipped brand accent via the legacy block.
     */
    public function __construct(bool $applyTheme = true)
    {
        // Resolve the service inside the constructor rather than injecting it,
        // so $applyTheme is the sole constructor parameter. Mixing an injected
        // dependency with a data attribute misbinds the attribute, which left
        // the admin still picking up the storefront theme.
        $themes = app(ThemeService::class);

        $css = (string) (@file_get_contents(resource_path('css/app.css')) ?: '');

        // Drop @import "tailwindcss"; and @source globs: the browser build
        // supplies Tailwind itself and scans the live DOM for classes.
        $css = (string) preg_replace('/^[ \t]*@(?:import|source)\b[^;]*;[ \t]*\R?/m', '', $css);

        $theme = $applyTheme ? $themes->active() : null;

        if (! $theme) {
            $css .= $this->legacyAccentBlock($themes);
        } else {
            $css .= $themes->cssTokens($theme);
        }

        $this->tokens = $css;
    }

    /**
     * Pre-Theme-Manager behaviour: re-tint the ramp from config('brand.accent').
     * Kept so upgrading an install that never opens the Theme Manager changes
     * nothing about how it looks.
     */
    private function legacyAccentBlock(ThemeService $themes): string
    {
        $accent = (string) config('brand.accent');

        if (! $accent || strtolower($accent) === '#ea580c') {
            return '';
        }

        $lines = [];

        foreach ($themes->ramp(['accent' => $accent]) as $step => $value) {
            $lines[] = "  --color-brand-{$step}: {$value};";
        }

        return "\n@theme {\n".implode("\n", $lines)."\n}\n";
    }

    public function render()
    {
        return view('components.tailwind-cdn');
    }
}
