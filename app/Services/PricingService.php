<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Discount;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\TaxRule;
use App\Support\Money;

/**
 * The single source of truth for what a basket costs.
 *
 * Both the cart page and checkout call quote(); nothing else adds money up.
 * That matters because the classic e-commerce bug is a cart summary and a
 * charge that were computed by two different code paths and disagree by a cent.
 *
 * Order of operations (the conventional one, and the one tax authorities
 * expect): line subtotal -> discount -> shipping -> tax on the discounted
 * subtotal (plus shipping when the rule says so) -> total.
 *
 * Everything is integer cents end to end.
 */
class PricingService
{
    /**
     * Price a cart. `$address` is the shipping address array (or null before
     * checkout collects one); `$rateId` is the shopper's chosen shipping rate.
     *
     * @return array{
     *   subtotal_cents:int, discount_cents:int, shipping_cents:int, tax_cents:int,
     *   total_cents:int, discount:?Discount, discount_error:?string,
     *   shipping_rates:\Illuminate\Support\Collection, shipping_rate:?ShippingRate,
     *   tax_rule:?TaxRule, lines:array, formatted:array
     * }
     */
    public function quote(Cart $cart, ?array $address = null, ?int $rateId = null): array
    {
        $cart->loadMissing('items.variant', 'items.product.collections');

        // ---- 1. Line subtotal -------------------------------------------
        $lines = [];
        $subtotal = 0;

        foreach ($cart->items as $item) {
            $lineTotal = $item->unit_price_cents * $item->quantity;
            $subtotal += $lineTotal;
            $lines[$item->id] = [
                'item' => $item,
                'subtotal_cents' => $lineTotal,
                'discount_cents' => 0,
                'tax_cents' => 0,
            ];
        }

        // ---- 2. Discount -------------------------------------------------
        $discount = Discount::findByCode($cart->discount_code);
        $discountError = null;
        $discountTotal = 0;
        $freeShipping = false;

        if ($discount) {
            $discountError = $discount->rejectionReason($subtotal, $cart->customer, $cart->email);

            if ($discountError === null) {
                // Only the eligible lines contribute to the discountable base.
                $eligible = [];
                foreach ($lines as $id => $line) {
                    $product = $line['item']->product;
                    if ($product && $discount->appliesToItem($product)) {
                        $eligible[$id] = $line['subtotal_cents'];
                    }
                }
                $eligibleTotal = array_sum($eligible);

                if ($discount->type === 'free_shipping') {
                    $freeShipping = true;
                } elseif ($eligibleTotal > 0) {
                    $discountTotal = $discount->type === 'percentage'
                        ? Money::applyBps($eligibleTotal, (int) $discount->value)
                        : min((int) $discount->value, $eligibleTotal);

                    // Spread it across the eligible lines so per-line tax is
                    // computed on the actually-discounted amount, and the parts
                    // sum to exactly the whole.
                    foreach (Money::allocate($discountTotal, $eligible) as $id => $share) {
                        $lines[$id]['discount_cents'] = $share;
                    }
                }
            }
        }

        // ---- 3. Shipping --------------------------------------------------
        $discountedSubtotal = $subtotal - $discountTotal;
        $needsShipping = $cart->items->contains(fn ($i) => $i->product?->requires_shipping ?? true);

        $availableRates = collect();
        $chosenRate = null;
        $shippingTotal = 0;

        if ($needsShipping && $address) {
            $zone = ShippingZone::forAddress($address['country'] ?? null, $address['state'] ?? null);

            if ($zone) {
                $availableRates = $zone->rates
                    ->where('is_active', true)
                    ->filter(fn (ShippingRate $r) => $r->availableFor($discountedSubtotal, $cart->weight_grams))
                    ->values();

                $chosenRate = $availableRates->firstWhere('id', $rateId) ?? $availableRates->first();

                if ($chosenRate) {
                    $shippingTotal = $freeShipping ? 0 : $chosenRate->priceFor($discountedSubtotal);
                }
            }
        }

        // ---- 4. Tax --------------------------------------------------------
        $taxTotal = 0;
        $taxRule = null;

        if ($address) {
            $inclusive = config('shop.tax_mode') === 'inclusive';

            foreach ($lines as $id => $line) {
                $product = $line['item']->product;
                $rule = TaxRule::resolve(
                    $address['country'] ?? null,
                    $address['state'] ?? null,
                    $address['postcode'] ?? null,
                    $product?->tax_class ?? 'standard'
                );

                if (! $rule) {
                    continue;
                }

                $taxRule ??= $rule;
                $taxable = $line['subtotal_cents'] - $line['discount_cents'];

                // Inclusive pricing means the listed price already contains tax,
                // so we extract it rather than adding it on top.
                $lineTax = $inclusive
                    ? (int) round($taxable - ($taxable * 10000) / (10000 + $rule->rate_bps))
                    : Money::applyBps($taxable, (int) $rule->rate_bps);

                $lines[$id]['tax_cents'] = $lineTax;
                $taxTotal += $lineTax;
            }

            // Shipping is taxed only when the winning rule says it should be.
            if ($shippingTotal > 0 && $taxRule?->applies_to_shipping && config('shop.tax_shipping')) {
                $taxTotal += Money::applyBps($shippingTotal, (int) $taxRule->rate_bps);
            }
        }

        // ---- 5. Total -------------------------------------------------------
        // Inclusive tax is already inside the line prices; adding it again would
        // double-charge.
        $total = config('shop.tax_mode') === 'inclusive'
            ? $discountedSubtotal + $shippingTotal
            : $discountedSubtotal + $shippingTotal + $taxTotal;

        return [
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discountTotal,
            'shipping_cents' => $shippingTotal,
            'tax_cents' => $taxTotal,
            'total_cents' => max(0, $total),
            'discount' => $discountError === null ? $discount : null,
            'discount_error' => $discountError,
            'shipping_rates' => $availableRates,
            'shipping_rate' => $chosenRate,
            'tax_rule' => $taxRule,
            'needs_shipping' => $needsShipping,
            'lines' => $lines,
            // Pre-formatted so Blade prints strings and never calls a helper.
            'formatted' => [
                'subtotal' => Money::format($subtotal),
                'discount' => Money::format($discountTotal),
                'shipping' => $shippingTotal > 0 ? Money::format($shippingTotal) : 'Free',
                'tax' => Money::format($taxTotal),
                'total' => Money::format(max(0, $total)),
            ],
        ];
    }
}
