{{--
    Every Blade directive in this file starts a line. Blade does not compile a
    directive preceded by a word character but DOES compile the matching @endif,
    which silently mis-scopes the template. Anything inline is precomputed in the
    Mailable. See emails/orders/confirmation.blade.php for the full note.
--}}
<x-mail::message>
# Quote {{ $quote->number }}

Hi {{ $quote->customer?->name ?? 'there' }}, here is your quote from {{ $storeName }}.

**{{ $quote->title }}**

@if ($validUntil)
**Valid Until:** {{ $validUntil }}
@endif

<x-mail::table>
| Service | Qty | Total |
| :--- | :-: | ----: |
@foreach ($rows as $row)
| {{ $row['name'] }} | {{ $row['quantity'] }} | {{ $row['total'] }} |
@endforeach
</x-mail::table>

<x-mail::table>
| Summary | |
| :--- | ----: |
| Subtotal | {{ $quote->subtotal_formatted }} |
| Discount | {{ $quote->discount_formatted }} |
| Tax | {{ $quote->tax_formatted }} |
| **Total** | **{{ $quote->total_formatted }}** |
</x-mail::table>

@if ($quote->message)
{{ $quote->message }}
@endif

<x-mail::button :url="$quoteUrl">
Review &amp; Respond
</x-mail::button>

You can accept or decline this quote online using the button above.

Thanks,<br>
{{ $storeName }}
</x-mail::message>
