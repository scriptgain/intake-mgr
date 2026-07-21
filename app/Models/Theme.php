<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A named set of storefront design tokens.
 *
 * Tokens are stored as JSON and always read back through tokens(), which merges
 * the row over ShopMGR's shipped defaults. That means a theme saved by an older
 * release keeps working when a newer release adds a token: the new token simply
 * falls back to its default instead of rendering as an empty CSS value.
 */
class Theme extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'is_active', 'is_preset',
        'tokens', 'logo_path', 'favicon_path',
    ];

    protected $casts = [
        'tokens' => 'array',
        'is_active' => 'boolean',
        'is_preset' => 'boolean',
    ];

    /** Shipped defaults, i.e. the look ShopMGR has out of the box. */
    public static function defaultTokens(): array
    {
        return [
            'accent' => '#ea580c',
            // Explicit brand ramp. Null means "derive the ramp from the accent"
            // with the same colour-mix formula the admin chrome uses.
            'ramp' => null,
            'chrome' => '#17132a',
            'chrome_soft' => '#221b3d',
            'shop_bg' => '#fbfaf9',
            'shop_ink' => '#1c1917',
            'shop_muted' => '#78716c',
            'shop_line' => '#e7e5e4',
            // instrument | system | serif | mono. All self-hosted or system
            // stacks; nothing here ever calls out to a font CDN.
            'font_family' => 'instrument',
            'font_scale' => 100,   // percent, applied to the root font size
            'radius' => 100,       // percent of Tailwind's default radius scale
            'spacing' => 100,      // percent of Tailwind's 0.25rem spacing unit
        ];
    }

    /** This theme's tokens, merged over the defaults. */
    public function tokens(): array
    {
        return array_merge(self::defaultTokens(), is_array($this->tokens) ? $this->tokens : []);
    }

    public function token(string $key, $default = null)
    {
        return $this->tokens()[$key] ?? $default;
    }

    /**
     * The four colours that identify a theme at a glance, for the swatch strip
     * on the themes list. Here rather than in the view, per the no-logic-in-
     * views rule.
     *
     * @return array<int, array{label: string, color: string}>
     */
    public function swatches(): array
    {
        $t = $this->tokens();

        return [
            ['label' => 'Accent', 'color' => $t['accent']],
            ['label' => 'Chrome', 'color' => $t['chrome']],
            ['label' => 'Page', 'color' => $t['shop_bg']],
            ['label' => 'Text', 'color' => $t['shop_ink']],
        ];
    }

    /** Short human summary of the non-colour tokens. */
    public function typographyLabel(): string
    {
        $t = $this->tokens();

        $family = [
            'instrument' => 'Instrument Sans',
            'system' => 'System Sans',
            'serif' => 'System Serif',
            'mono' => 'System Mono',
        ][$t['font_family']] ?? 'Instrument Sans';

        return $family.' · '.$t['font_scale'].'% · Radius '.$t['radius'].'%';
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? asset($this->favicon_path) : null;
    }
}
