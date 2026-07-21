<?php

namespace App\View\Components;

use App\Support\Money as MoneyFormatter;
use Illuminate\View\Component;

/**
 * Renders an amount with the currency symbol and the fractional part split out,
 * so the display treatment can step them back and let the significant digits
 * carry the number.
 *
 * The splitting lives here rather than in the template because the view layer
 * is markup-only: a Blade file should not be running string surgery on a
 * formatted price.
 *
 * Accepts either cents (preferred, exact) or an already-formatted string, since
 * several models expose only a *_formatted accessor.
 */
class Money extends Component
{
    public string $symbol = '';

    public string $whole = '';

    public string $fraction = '';

    public bool $negative = false;

    public function __construct(
        ?int $cents = null,
        ?string $formatted = null,
        public string $size = 'inherit',
    ) {
        $this->split($formatted ?? MoneyFormatter::format($cents));
    }

    /**
     * Break "-$1,299.50" into its sign, symbol, whole and fractional parts.
     * Works for any symbol position and for zero-decimal currencies, because
     * it keys off the configured decimal separator rather than assuming "$".
     */
    private function split(string $value): void
    {
        $value = trim($value);

        if (str_starts_with($value, '-') || str_starts_with($value, "\u{2212}")) {
            $this->negative = true;
            $value = ltrim($value, "-\u{2212}");
        }

        // Everything before the first digit is the currency symbol.
        if (preg_match('/^([^0-9]*)(.*)$/u', $value, $m)) {
            $this->symbol = $m[1];
            $value = $m[2];
        }

        $pos = strrpos($value, '.');

        if ($pos === false) {
            $this->whole = $value;

            return;
        }

        $this->whole = substr($value, 0, $pos);
        $this->fraction = substr($value, $pos + 1);
    }

    /** Size utilities for the whole-number part, keyed by a named scale. */
    public function sizeClass(): string
    {
        return [
            'display' => 'text-4xl font-semibold',
            'lg' => 'text-2xl font-semibold',
            'md' => 'text-lg font-semibold',
            'inherit' => '',
        ][$this->size] ?? '';
    }

    public function render()
    {
        return view('components.money');
    }
}
