<x-layouts.shop title="Order Confirmed">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-10">
        {{-- Success mark reads left of the heading, not stacked above centred copy. --}}
        <div class="flex items-start gap-4 sm:gap-5">
            <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-200 shrink-0">
                <x-icon name="check-circle" class="w-7 h-7" />
            </span>
            <div class="min-w-0">
                <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">Thank You For Your Order</h1>
                <p class="mt-3 text-shop-muted max-w-2xl">
                    Order <span class="font-medium text-shop-ink">{{ $order->number }}</span> was placed on {{ $order->created_at->format('F j, Y') }}.
                    A confirmation has been sent to {{ $order->email }}.
                </p>
            </div>
        </div>
    </section>

    <div class="section-divider"></div>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

            <div class="min-w-0 lg:col-span-2 space-y-8">
                @if ($isTestPayment)
                    <div class="flex items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-inset ring-amber-200">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-700 ring-1 ring-amber-200 shrink-0">
                            <x-icon name="warning" class="w-4 h-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-amber-900">Test Order</p>
                            <p class="text-sm text-amber-800">This order was placed while the store was in Stripe test mode. No real payment was taken.</p>
                        </div>
                    </div>
                @endif

                @if ($awaitingPayment)
                    {{-- The webhook has not landed yet. Saying "confirmed" here
                         would be a lie the shopper discovers later. --}}
                    <x-alert type="warn" title="Confirming Your Payment">
                        Your bank is still confirming this payment. It usually completes within a minute. We will email you as soon as it does, and this page will show the final status when you refresh.
                    </x-alert>
                @endif

                @if ($instructions)
                    <x-alert type="info" title="Payment Instructions">
                        <div class="whitespace-pre-line">{{ $instructions }}</div>
                    </x-alert>
                @endif

                <div>
                    <h2 class="text-lg font-semibold text-shop-ink mb-4">Items</h2>
                    <div class="divide-y divide-shop-line">
                        @foreach ($order->items as $item)
                            <div class="py-4 flex items-center gap-4">
                                <span class="shop-media w-16 h-16 rounded-lg shrink-0">
                                    @if ($item->image_url)
                                        <img src="{{ $item->image_url }}" alt="{{ $item->name }}">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-shop-muted">
                                            <x-icon name="bag" class="w-6 h-6" />
                                        </div>
                                    @endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-shop-ink truncate">{{ $item->name }}</p>
                                    @if ($item->variant_label)
                                        <p class="text-xs text-shop-muted">{{ $item->variant_label }}</p>
                                    @endif
                                    <p class="text-xs text-shop-muted">Qty {{ $item->quantity }}</p>
                                </div>
                                <p class="text-sm font-medium text-shop-ink tabular shrink-0">{{ $item->total_formatted }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($order->customer_note)
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-2">Order Notes</h2>
                        <p class="text-sm text-shop-muted whitespace-pre-line">{{ $order->customer_note }}</p>
                    </div>
                @endif

                @if ($order->shipping_address)
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-2">Shipping Address</h2>
                        <address class="not-italic text-sm text-shop-muted leading-relaxed">
                            {{ $order->shipping_address['first_name'] ?? '' }} {{ $order->shipping_address['last_name'] ?? '' }}<br>
                            @if (! empty($order->shipping_address['company']))
                                {{ $order->shipping_address['company'] }}<br>
                            @endif
                            {{ $order->shipping_address['line1'] ?? '' }}<br>
                            @if (! empty($order->shipping_address['line2']))
                                {{ $order->shipping_address['line2'] }}<br>
                            @endif
                            {{ $order->shipping_address['city'] ?? '' }}@if (! empty($order->shipping_address['state'])), {{ $order->shipping_address['state'] }}@endif {{ $order->shipping_address['postcode'] ?? '' }}<br>
                            {{ $order->shipping_address['country'] ?? '' }}
                        </address>
                    </div>
                @endif
            </div>

            <div>
                <x-card title="Order Summary">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-shop-muted">Subtotal</dt><dd class="tabular text-shop-ink">{{ $order->subtotal_formatted }}</dd></div>
                        @if ($order->discount_cents > 0)
                            <div class="flex justify-between"><dt class="text-shop-muted">Discount</dt><dd class="tabular text-emerald-600">&minus;{{ $order->discount_formatted }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-shop-muted">Shipping</dt><dd class="tabular text-shop-ink">{{ $order->shipping_formatted }}</dd></div>
                        <div class="flex justify-between"><dt class="text-shop-muted">Tax</dt><dd class="tabular text-shop-ink">{{ $order->tax_formatted }}</dd></div>
                        <div class="pt-3 border-t border-shop-line flex justify-between text-base font-semibold">
                            <dt class="text-shop-ink">Total</dt><dd class="tabular text-shop-ink">{{ $order->total_formatted }}</dd>
                        </div>
                    </dl>

                    <x-slot:footer>
                        <x-button href="{{ route('shop.catalog') }}" class="w-full justify-center">Continue Shopping</x-button>
                    </x-slot:footer>
                </x-card>
            </div>
        </div>
    </section>

</x-layouts.shop>
