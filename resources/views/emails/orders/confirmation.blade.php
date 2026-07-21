{{--
    Every Blade directive in this file starts a line.

    Blade does not compile a directive preceded by a word character (its
    statement regex requires \B before the @), but it DOES compile the matching
    @endif when that one happens to follow a non-word character. The result is a
    template that either fails with "unexpected endif" or, worse, compiles with
    two errors cancelling out and silently mis-scopes its own conditionals.
    Anything that would need an inline @if is precomputed in the Mailable.
--}}
<x-mail::message>
# Thank You For Your Order

Hi {{ $order->customer_name }}, we have your order and it is confirmed.

@if ($isTestOrder)
<x-mail::panel>
**Test Order.** This order was placed while the store was in Stripe test mode. No real payment was taken.
</x-mail::panel>
@endif

**Order Number:** {{ $order->number }}

**Placed:** {{ $order->created_at->format(config('shop.date_format').' '.config('shop.time_format')) }}

**Payment:** {{ $paymentLine }}

## Your Items

<x-mail::table>
| Item | Qty | Price |
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
| {{ $shippingLabel }} | {{ $order->shipping_formatted }} |
| Tax | {{ $order->tax_formatted }} |
| **Total** | **{{ $order->total_formatted }}** |
@if ($order->refunded_cents > 0)
| Refunded | -{{ $order->refunded_formatted }} |
@endif
</x-mail::table>

@if ($shipping)
## Shipping To

@foreach ($shipping as $line)
{{ $line }}
@endforeach
@endif

@if ($billing && $billing !== $shipping)
## Billing Address

@foreach ($billing as $line)
{{ $line }}
@endforeach
@endif

@if ($manualInstructions)
<x-mail::panel>
**How To Pay**

{{ $manualInstructions }}
</x-mail::panel>
@endif

@if ($order->customer_note)
**Your Note:** {{ $order->customer_note }}
@endif

<x-mail::button :url="$orderUrl">
View Your Order
</x-mail::button>

Thanks,

{{ $storeName }}
</x-mail::message>
