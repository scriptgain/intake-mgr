<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The customer's receipt: what they bought, what it cost, where it is going.
 *
 * All the view data is resolved HERE, not in the template, per the no-PHP-in-
 * views rule. The template prints strings.
 */
class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order '.$this->order->number.' Confirmed: '.config('shop.store_name'),
            replyTo: array_filter([config('shop.store_email')]),
        );
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing('items');

        return new Content(
            markdown: 'emails.orders.confirmation',
            with: [
                'order' => $order,
                'storeName' => config('shop.store_name'),
                'items' => $order->items,
                'shipping' => self::formatAddress($order->shipping_address),
                'billing' => self::formatAddress($order->billing_address),
                // A signed link so a guest can reach their own order without an
                // account, and cannot walk the URL to anyone else's.
                'orderUrl' => URL::temporarySignedRoute(
                    'shop.checkout.confirmation',
                    now()->addDays(60),
                    ['order' => $order->number]
                ),
                // Brand and last four only. Nothing else about the card is
                // stored, so nothing else can be printed.
                'cardLine' => $order->card_brand
                    ? ucfirst($order->card_brand).' ending '.$order->card_last4
                    : null,
                'isPaid' => $order->is_paid,
                'isTestOrder' => ! $order->livemode && $order->payment_gateway === 'stripe',
                // Precomputed so the template never needs an inline @if in the
                // middle of a line of text. Blade does NOT compile a directive
                // that is preceded by a word character, but DOES compile the
                // matching @endif, which silently unbalances the template.
                'rows' => self::rows($order),
                'paymentLine' => self::paymentLine($order),
                'discountLabel' => $order->discount_code
                    ? 'Discount ('.$order->discount_code.')'
                    : 'Discount',
                'shippingLabel' => $order->shipping_method
                    ? 'Shipping ('.$order->shipping_method.')'
                    : 'Shipping',
                'manualInstructions' => $order->payment_gateway === 'manual'
                    ? \App\Models\Setting::get('manual_instructions')
                    : null,
            ],
        );
    }

    /**
     * One printable row per line item: name, a single meta line combining the
     * variant and SKU, the quantity and the total. Built here so the email
     * table is a plain loop with no inline conditionals.
     */
    public static function rows(Order $order): array
    {
        return $order->items->map(fn ($item) => [
            'name' => $item->name,
            'meta' => trim(implode(' · ', array_filter([
                $item->variant_label,
                $item->sku ? 'SKU '.$item->sku : null,
            ]))),
            'quantity' => $item->quantity,
            'total' => $item->total_formatted,
        ])->all();
    }

    /** "Received, Visa ending 4242" / "Awaiting Payment". */
    public static function paymentLine(Order $order): string
    {
        if (! $order->is_paid) {
            return 'Awaiting Payment';
        }

        return $order->card_brand
            ? 'Received, '.ucfirst($order->card_brand).' ending '.$order->card_last4
            : 'Received';
    }

    /** Flatten a stored address array into printable lines. */
    public static function formatAddress(?array $address): array
    {
        if (! $address) {
            return [];
        }

        $name = trim(($address['first_name'] ?? '').' '.($address['last_name'] ?? ''));
        $cityLine = trim(implode(' ', array_filter([
            trim(($address['city'] ?? '').(($address['city'] ?? '') && ($address['state'] ?? '') ? ',' : '')),
            $address['state'] ?? null,
            $address['postcode'] ?? null,
        ])));

        return array_values(array_filter([
            $name ?: null,
            $address['company'] ?? null,
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            $cityLine ?: null,
            $address['country'] ?? null,
        ]));
    }
}
