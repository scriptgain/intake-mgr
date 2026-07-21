<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The merchant's "you have an order" mail.
 *
 * Same contents as the customer receipt plus the operational bits staff need:
 * the customer's contact details, the payment state, and a direct link into the
 * admin order screen.
 */
class OrderPlacedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $prefix = $this->order->livemode || $this->order->payment_gateway !== 'stripe' ? '' : '[TEST] ';

        return new Envelope(
            subject: $prefix.'New Order '.$this->order->number.': '.$this->order->total_formatted,
            replyTo: array_filter([$this->order->email]),
        );
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing('items');

        return new Content(
            markdown: 'emails.orders.merchant',
            with: [
                'order' => $order,
                'storeName' => config('shop.store_name'),
                'items' => $order->items,
                'shipping' => OrderConfirmation::formatAddress($order->shipping_address),
                'billing' => OrderConfirmation::formatAddress($order->billing_address),
                'adminUrl' => route('orders.show', $order),
                'cardLine' => $order->card_brand
                    ? ucfirst($order->card_brand).' ending '.$order->card_last4
                    : null,
                'isPaid' => $order->is_paid,
                'isTestOrder' => ! $order->livemode && $order->payment_gateway === 'stripe',
                // Precomputed for the same reason as the customer receipt: an
                // inline @if mid-line is not compiled by Blade while its @endif
                // is, which silently mis-scopes the template.
                'rows' => OrderConfirmation::rows($order),
                'paymentLine' => ($order->payment_gateway === 'stripe' ? 'Card' : 'Manual / Offline')
                    .($order->is_paid ? ', received' : ', awaiting payment'),
                'discountLabel' => $order->discount_code
                    ? 'Discount ('.$order->discount_code.')'
                    : 'Discount',
            ],
        );
    }
}
