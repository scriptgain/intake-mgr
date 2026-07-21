<?php

namespace App\Services;

use App\Models\Theme;

/**
 * The theme token pipeline.
 *
 * ShopMGR has no build step. Tailwind v4 runs in the browser and compiles the
 * CSS it finds in the page's <style type="text/tailwindcss"> block, which
 * x-tailwind-cdn fills from resources/css/app.css at runtime. This service adds
 * one more @theme block after that one, built from the active theme's tokens.
 * Later @theme declarations win, so a theme re-tints the whole product without
 * touching app.css, without npm, and without a rebuild.
 *
 * Two blocks are emitted on purpose:
 *
 *   1. cssTokens() goes inside the Tailwind block, so the browser build emits
 *      real utilities for the theme's values (bg-brand-600, rounded-lg,
 *      p-4 and so on all change).
 *   2. rootVars() is a plain <style> after it. The browser build injects its
 *      CSS asynchronously, and a plain :root block guarantees the custom
 *      properties are correct from first paint rather than racing it.
 *
 * Everything that reaches a stylesheet is sanitised here, not at the form
 * boundary, because this is the last line before merchant input becomes CSS.
 */
class ThemeService
{
    private ?Theme $memo = null;

    private bool $memoSet = false;

    /**
     * The theme in force for this request: a session preview if the admin is
     * previewing one, otherwise the active theme.
     */
    public function active(): ?Theme
    {
        if ($this->memoSet) {
            return $this->memo;
        }

        $this->memoSet = true;

        try {
            // No Schema::hasTable() guard: it costs an information_schema query
            // on every page view. A missing table throws and the catch below
            // falls back to the shipped look, which is the same outcome.
            if ($preview = $this->previewTheme()) {
                return $this->memo = $preview;
            }

            return $this->memo = Theme::where('is_active', true)->first();
        } catch (\Throwable $e) {
            // No themes table yet (fresh install, mid-migration): the shipped
            // app.css look is a perfectly good fallback.
            return $this->memo = null;
        }
    }

    public function forget(): void
    {
        $this->memo = null;
        $this->memoSet = false;
    }

    /* ------------------------------------------------------------------ *
     * Preview
     * ------------------------------------------------------------------ */

    public function previewTheme(): ?Theme
    {
        $id = $this->previewId();

        return $id ? Theme::find($id) : null;
    }

    public function previewId(): ?int
    {
        try {
            if (app()->runningInConsole() || ! app('session')->isStarted()) {
                return null;
            }

            $preview = session('theme_preview');

            if (! is_array($preview)) {
                return null;
            }

            $ttl = (int) config('templates.preview_minutes', 20) * 60;

            if ((time() - (int) ($preview['at'] ?? 0)) >= $ttl) {
                return null;
            }

            return (int) ($preview['id'] ?? 0) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function startPreview(Theme $theme): void
    {
        session(['theme_preview' => ['id' => $theme->id, 'at' => time()]]);
        $this->forget();
    }

    public function stopPreview(): void
    {
        session()->forget('theme_preview');
        $this->forget();
    }

    /* ------------------------------------------------------------------ *
     * CSS generation
     * ------------------------------------------------------------------ */

    /** The @theme block appended to the in-browser Tailwind source. */
    public function cssTokens(?Theme $theme = null): string
    {
        $theme ??= $this->active();

        if (! $theme) {
            return '';
        }

        $t = $theme->tokens();
        $ramp = $this->ramp($t);

        $lines = [];

        foreach ($ramp as $step => $value) {
            $lines[] = "  --color-brand-{$step}: {$value};";
        }

        $lines[] = '  --color-chrome: '.$this->color($t['chrome'], '#17132a').';';
        $lines[] = '  --color-chrome-soft: '.$this->color($t['chrome_soft'], '#221b3d').';';
        $lines[] = '  --color-shop-bg: '.$this->color($t['shop_bg'], '#fbfaf9').';';
        $lines[] = '  --color-shop-ink: '.$this->color($t['shop_ink'], '#1c1917').';';
        $lines[] = '  --color-shop-muted: '.$this->color($t['shop_muted'], '#78716c').';';
        $lines[] = '  --color-shop-line: '.$this->color($t['shop_line'], '#e7e5e4').';';
        $lines[] = '  --font-sans: '.$this->fontStack($t['font_family']).';';

        // Tailwind v4 derives every spacing utility from one --spacing unit, so
        // a single token changes the whole rhythm of the page.
        $spacing = $this->percent($t['spacing'], 70, 160);
        $lines[] = '  --spacing: '.round(0.25 * $spacing / 100, 4).'rem;';

        foreach ($this->radiusScale($t['radius']) as $name => $value) {
            $lines[] = "  --radius-{$name}: {$value};";
        }

        return "\n@theme {\n".implode("\n", $lines)."\n}\n";
    }

    /**
     * The same tokens as plain custom properties, so they are correct before
     * the in-browser Tailwind build has finished injecting its own CSS.
     */
    public function rootVars(?Theme $theme = null): string
    {
        $theme ??= $this->active();

        if (! $theme) {
            return '';
        }

        $t = $theme->tokens();
        $lines = [];

        foreach ($this->ramp($t) as $step => $value) {
            $lines[] = "--color-brand-{$step}: {$value};";
        }

        $lines[] = '--accent: '.$this->color($t['accent'], '#ea580c').';';
        $lines[] = '--color-chrome: '.$this->color($t['chrome'], '#17132a').';';
        $lines[] = '--color-chrome-soft: '.$this->color($t['chrome_soft'], '#221b3d').';';
        $lines[] = '--color-shop-bg: '.$this->color($t['shop_bg'], '#fbfaf9').';';
        $lines[] = '--color-shop-ink: '.$this->color($t['shop_ink'], '#1c1917').';';
        $lines[] = '--color-shop-muted: '.$this->color($t['shop_muted'], '#78716c').';';
        $lines[] = '--color-shop-line: '.$this->color($t['shop_line'], '#e7e5e4').';';
        $lines[] = '--font-sans: '.$this->fontStack($t['font_family']).';';

        $css = ':root{'.implode('', $lines).'}';

        // Typography scale rides on the root font size: Tailwind's type and
        // spacing scales are rem-based, so one declaration scales the page
        // proportionally instead of needing an override per utility.
        $scale = $this->percent($t['font_scale'], 85, 125);

        if ($scale !== 100) {
            $css .= 'html{font-size:'.$scale.'%;}';
        }

        return $css;
    }

    /* ------------------------------------------------------------------ *
     * Token helpers
     * ------------------------------------------------------------------ */

    /**
     * The 11-step brand ramp: either the theme's explicit ramp, or one derived
     * from the accent with the colour-mix formula the product already used.
     *
     * @return array<string, string>
     */
    public function ramp(array $tokens): array
    {
        $steps = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];

        if (! empty($tokens['ramp']) && is_array($tokens['ramp'])) {
            $out = [];

            foreach ($steps as $step) {
                if (! empty($tokens['ramp'][$step])) {
                    $out[$step] = $this->color($tokens['ramp'][$step], '#ea580c');
                }
            }

            if (count($out) === count($steps)) {
                return $out;
            }
        }

        $accent = $this->color($tokens['accent'] ?? '#ea580c', '#ea580c');

        $mix = [
            '50' => ['white', 92], '100' => ['white', 85], '200' => ['white', 72],
            '300' => ['white', 55], '400' => ['white', 30], '500' => [null, 0],
            '600' => ['black', 12], '700' => ['black', 25], '800' => ['black', 40],
            '900' => ['black', 52], '950' => ['black', 68],
        ];

        $out = [];

        foreach ($mix as $step => [$with, $amount]) {
            $out[$step] = $with === null
                ? $accent
                : "color-mix(in srgb, {$accent}, {$with} {$amount}%)";
        }

        return $out;
    }

    /** Self-hosted or system font stacks only. No third-party font requests. */
    public function fontStack(?string $key): string
    {
        return [
            'instrument' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji'",
            'system' => "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji'",
            'serif' => "ui-serif, Georgia, Cambria, 'Times New Roman', Times, serif",
            'mono' => "ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace",
        ][$key] ?? "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";
    }

    /** Human labels for the family picker. */
    public function fontChoices(): array
    {
        return [
            'instrument' => 'Instrument Sans (Bundled)',
            'system' => 'System Sans',
            'serif' => 'System Serif',
            'mono' => 'System Mono',
        ];
    }

    /** Tailwind's radius scale, multiplied by the theme's radius percentage. */
    public function radiusScale($percent): array
    {
        $factor = $this->percent($percent, 0, 220) / 100;

        $base = [
            'xs' => 0.125, 'sm' => 0.25, 'md' => 0.375, 'lg' => 0.5,
            'xl' => 0.75, '2xl' => 1.0, '3xl' => 1.5, '4xl' => 2.0,
        ];

        $out = [];

        foreach ($base as $name => $rem) {
            $out[$name] = round($rem * $factor, 4).'rem';
        }

        return $out;
    }

    /** A hex colour, or the fallback. Nothing else ever reaches a stylesheet. */
    public function color($value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }

    private function percent($value, int $min, int $max): int
    {
        $value = (int) $value;

        return max($min, min($max, $value ?: 100));
    }

    /* ------------------------------------------------------------------ *
     * Activation, export, import
     * ------------------------------------------------------------------ */

    public function activate(Theme $theme): void
    {
        Theme::where('is_active', true)->update(['is_active' => false]);
        $theme->forceFill(['is_active' => true])->save();
        $this->forget();
    }

    /** A theme as a portable JSON payload. */
    public function export(Theme $theme): array
    {
        return [
            'format' => 'shopmgr.theme',
            'version' => 1,
            'name' => $theme->name,
            'description' => $theme->description,
            'exported_at' => now()->toIso8601String(),
            'tokens' => $theme->tokens(),
        ];
    }

    /**
     * Validate an imported payload down to tokens we are willing to render.
     *
     * @return array{ok: bool, error: ?string, name: ?string, description: ?string, tokens: array}
     */
    public function parseImport(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return ['ok' => false, 'error' => 'That file is not valid JSON.', 'name' => null, 'description' => null, 'tokens' => []];
        }

        if (($data['format'] ?? null) !== 'shopmgr.theme') {
            return ['ok' => false, 'error' => 'That JSON is not a ShopMGR theme export (missing "format": "shopmgr.theme").', 'name' => null, 'description' => null, 'tokens' => []];
        }

        $tokens = is_array($data['tokens'] ?? null) ? $data['tokens'] : [];
        $defaults = Theme::defaultTokens();
        $clean = [];

        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $tokens)) {
                $clean[$key] = $default;

                continue;
            }

            if ($key === 'ramp') {
                $clean[$key] = is_array($tokens[$key]) ? $tokens[$key] : null;
            } elseif (in_array($key, ['font_scale', 'radius', 'spacing'], true)) {
                $clean[$key] = (int) $tokens[$key];
            } elseif ($key === 'font_family') {
                $clean[$key] = array_key_exists($tokens[$key], $this->fontChoices()) ? $tokens[$key] : 'instrument';
            } else {
                $clean[$key] = $this->color($tokens[$key], $default);
            }
        }

        return [
            'ok' => true,
            'error' => null,
            'name' => is_string($data['name'] ?? null) ? mb_substr($data['name'], 0, 60) : 'Imported Theme',
            'description' => is_string($data['description'] ?? null) ? mb_substr($data['description'], 0, 200) : null,
            'tokens' => $clean,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Shipped presets
     * ------------------------------------------------------------------ */

    /**
     * The themes ShopMGR ships with. "Rose" is the shipped default and
     * reproduces app.css exactly, so activating it is always a way back.
     */
    public function presets(): array
    {
        return [
            [
                'slug' => 'ember',
                'name' => 'Ember',
                'description' => 'The look IntakeMGR ships with: warm neutral surfaces, deep chrome, energetic orange accent.',
                'tokens' => array_merge(Theme::defaultTokens(), [
                    'accent' => '#ea580c',
                    'ramp' => [
                        '50' => '#fff7ed', '100' => '#ffedd5', '200' => '#fed7aa',
                        '300' => '#fdba74', '400' => '#fb923c', '500' => '#f97316',
                        '600' => '#ea580c', '700' => '#c2410c', '800' => '#9a3412',
                        '900' => '#7c2d12', '950' => '#431407',
                    ],
                ]),
            ],
            [
                'slug' => 'midnight-market',
                'name' => 'Midnight Market',
                'description' => 'Cool slate surfaces, near-black chrome, indigo accent. Tighter corners for a technical, catalogue-heavy shop.',
                'tokens' => array_merge(Theme::defaultTokens(), [
                    'accent' => '#4f46e5',
                    'ramp' => null,
                    'chrome' => '#0b1220',
                    'chrome_soft' => '#131c31',
                    'shop_bg' => '#f8fafc',
                    'shop_ink' => '#0f172a',
                    'shop_muted' => '#64748b',
                    'shop_line' => '#e2e8f0',
                    'font_family' => 'system',
                    'radius' => 55,
                    'spacing' => 95,
                ]),
            ],
            [
                'slug' => 'sand-and-ink',
                'name' => 'Sand & Ink',
                'description' => 'Warm paper background, ink-brown chrome, burnt amber accent, soft corners and a serif face. Suits craft and homeware catalogues.',
                'tokens' => array_merge(Theme::defaultTokens(), [
                    'accent' => '#b45309',
                    'ramp' => null,
                    'chrome' => '#1c1917',
                    'chrome_soft' => '#292524',
                    'shop_bg' => '#fbf7f0',
                    'shop_ink' => '#1c1917',
                    'shop_muted' => '#78716c',
                    'shop_line' => '#e7e0d5',
                    'font_family' => 'serif',
                    'font_scale' => 103,
                    'radius' => 150,
                    'spacing' => 105,
                ]),
            ],
        ];
    }

    /**
     * Create any missing preset. Idempotent, so it is safe to call on every
     * release without overwriting a preset the merchant has edited.
     */
    public function ensurePresets(): void
    {
        foreach ($this->presets() as $preset) {
            Theme::firstOrCreate(
                ['slug' => $preset['slug']],
                [
                    'name' => $preset['name'],
                    'description' => $preset['description'],
                    'tokens' => $preset['tokens'],
                    'is_preset' => true,
                    'is_active' => false,
                ]
            );
        }

        if (! Theme::where('is_active', true)->exists()) {
            $default = Theme::where('slug', 'ember')->first();

            if ($default) {
                $default->forceFill(['is_active' => true])->save();
            }
        }

        $this->forget();
    }
}
