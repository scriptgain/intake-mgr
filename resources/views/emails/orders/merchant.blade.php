{{--
    Every Blade directive in this file starts a line. See the note in
    confirmation.blade.php: a directive preceded by a word character is not
    compiled, while its matching @endif is, which silently unbalances the
    template. Anything needing an inline conditional is precomputed in the
    Mailable.
--}}
<x-mail::message>
# New Order: {{ $order->number }}

@if ($isTestOrder)
<x-mail::panel>
**Test Order.** Placed while the store was in Stripe test mode. No real money moved.
</x-mail::panel>
@endif

**Total:** {{ $order->total_formatted }}

**Payment:** {{ $paymentLine }}

@if ($cardLine)
**Card:** {{ $cardLine }}
@endif

**Placed:** {{ $order->created_at->format(config('shop.date_format').' '.config('shop.time_format')) }}

## Customer

**Name:** {{ $order->customer_name }}

**Email:** {{ $order->email }}

@if ($order->phone)
**Phone:** {{ $order->phone }}
@endif

## Items

<x-mail::table>
| Item | Qty | Total |
| :--- | :-: | ----: |
@foreach ($rows as $row)
| {{ $row['name'] }}{!! $row['meta'] ? '<br><small>'.e($row['meta']).'</small>' : '' !!} | {{ $row['quantity'] }} | {{ $row['total'] }} |
@endforeach
</x-mail::table>

<x-mail::table>
| Summary | |
| :--- | ----: |
| Subtotal | {{ $order->subtotal_formatted }} |
@if ($order->discount_cents > 0)
| {{ $discountLabel }} | -{{ $order->discount_formatted }} |
@endif
| Shipping | {{ $order->shipping_formatted }} |
| Tax | {{ $order->tax_formatted }} |
| **Total** | **{{ $order->total_formatted }}** |
</x-mail::table>

@if ($shipping)
## Ship To

@foreach ($shipping as $line)
{{ $line }}
@endforeach

@if ($order->shipping_method)
**Method:** {{ $order->shipping_method }}
@endif
@endif

@if ($billing && $billing !== $shipping)
## Billing Address

@foreach ($billing as $line)
{{ $line }}
@endforeach
@endif

@if ($order->customer_note)
<x-mail::panel>
**Customer Note:** {{ $order->customer_note }}
</x-mail::panel>
@endif

<x-mail::button :url="$adminUrl">
Open In Admin
</x-mail::button>

{{ $storeName }}
</x-mail::message>
