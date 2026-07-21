<?php

namespace App\Support;

/**
 * Every amount in ShopMGR is an integer number of minor units (cents). This is
 * the only place that turns one into a string for a human, so currency symbol,
 * decimal count, and rounding behave identically on the storefront, in the
 * admin, in emails, and in exports.
 *
 * Nothing here reads a float. Percentages arrive as basis points (1250 = 12.5%)
 * and multiplication happens in integer space before a single divide, so a
 * 12.5% discount on $19.99 lands on the same cent every time.
 */
class Money
{
    /** Format cents for display, e.g. 1999 -> "$19.99". */
    public static function format(?int $cents, bool $withSymbol = true): string
    {
        $cents ??= 0;
        $decimals = (int) config('shop.currency_decimals', 2);
        $divisor = 10 ** $decimals;

        $negative = $cents < 0;
        $abs = abs($cents);

        $formatted = number_format($abs / $divisor, $decimals);

        $out = ($withSymbol ? config('shop.currency_symbol', '$') : '').$formatted;

        return $negative ? '-'.$out : $out;
    }

    /**
     * Parse a merchant-entered amount ("19.99", "$19.99", "1,299") into cents.
     * Returns null for blank input so a nullable price column stays nullable.
     */
    public static function parse(string|float|int|null $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $input);
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return null;
        }

        $decimals = (int) config('shop.currency_decimals', 2);

        return (int) round(((float) $clean) * (10 ** $decimals));
    }

    /**
     * Apply a basis-point rate to an amount, rounding half-up to the cent.
     * 725 bps of 1999 cents = 145 (7.25% of $19.99 = $1.4493 -> $1.45).
     */
    public static function applyBps(int $cents, int $bps): int
    {
        return (int) round(($cents * $bps) / 10000);
    }

    /** Render basis points as a percentage string, e.g. 725 -> "7.25%". */
    public static function bpsToPercent(int $bps): string
    {
        return rtrim(rtrim(number_format($bps / 100, 2), '0'), '.').'%';
    }

    /** Parse a merchant-entered percentage ("7.25", "7.25%") into basis points. */
    public static function percentToBps(string|float|int|null $input): int
    {
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $input);

        return (int) round(((float) ($clean ?: 0)) * 100);
    }

    /**
     * Split an amount across N line items proportionally to their weights,
     * giving any rounding remainder to the largest lines. Used to spread an
     * order-level discount over line items so the sum of the parts is exactly
     * the whole — off-by-one-cent order totals are the classic bug here.
     *
     * @param  array<int|string, int>  $weights  keyed by line id
     * @return array<int|string, int>  same keys, allocated cents
     */
    public static function allocate(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum <= 0 || $total === 0) {
            return array_map(fn () => 0, $weights);
        }

        $out = [];
        $running = 0;
        foreach ($weights as $key => $weight) {
            $share = (int) floor(($total * $weight) / $sum);
            $out[$key] = $share;
            $running += $share;
        }

        // Hand the remainder out one cent at a time, heaviest line first.
        $remainder = $total - $running;
        if ($remainder > 0) {
            arsort($weights);
            foreach (array_keys($weights) as $key) {
                if ($remainder <= 0) {
                    break;
                }
                $out[$key]++;
                $remainder--;
            }
        }

        return $out;
    }
}
