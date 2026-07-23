<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A quote sent to the customer, with a public link to review, accept, or
 * decline it without needing an account. All view data is resolved here per the
 * no-PHP-in-views rule; the template prints strings.
 */
class QuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Quote '.$this->quote->number.' From '.config('shop.store_name'),
            replyTo: array_filter([config('shop.store_email')]),
        );
    }

    public function content(): Content
    {
        $quote = $this->quote->loadMissing('items');

        return new Content(
            markdown: 'emails.quotes.sent',
            with: [
                'quote' => $quote,
                'storeName' => config('shop.store_name'),
                'rows' => $quote->items->map(fn ($item) => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'total' => $item->total_formatted,
                ])->all(),
                'validUntil' => $quote->valid_until?->format('F j, Y'),
                'quoteUrl' => route('shop.quote.public', $quote->accept_token),
            ],
        );
    }
}
