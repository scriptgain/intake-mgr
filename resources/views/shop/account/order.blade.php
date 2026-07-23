<x-layouts.shop :title="'Invoice ' . $order->number">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <a href="{{ route('shop.account.invoices') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-shop-muted hover:text-shop-ink transition">
            <x-icon name="chevron-left" class="w-4 h-4" /> Back To Invoices
        </a>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight text-shop-ink">Invoice {{ $order->number }}</h1>
                <p class="mt-1 text-shop-muted">Issued {{ $order->created_at->format('F j, Y') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <x-badge :color="$order->financial_badge" dot>{{ \Illuminate\Support\Str::headline($order->financial_status) }}</x-badge>
                @if (($payUrl ?? null))
                    <x-button href="{{ $payUrl }}" icon="credit-card">Pay Now</x-button>
                @endif
            </div>
        </div>
    </section>

    <div class="section-divider"></div>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

            <div class="min-w-0 lg:col-span-2 space-y-8">
                <div>
                    <h2 class="text-lg font-semibold text-shop-ink mb-4">Items</h2>
                    <div class="space-y-3">
                        @foreach ($order->items as $item)
                            <div class="flex items-center gap-4 rounded-xl ring-1 ring-inset ring-shop-line bg-white px-4 py-3">
                                <span class="shop-media w-16 h-16 rounded-lg shrink-0">
                                    @if ($item->image_url)
                                        <img src="{{ $item->image_url }}" alt="{{ $item->name }}">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-brand-600">
                                            <x-icon name="bolt" class="w-6 h-6" />
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

                @if ($order->fulfillments->isNotEmpty())
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-4">Shipments</h2>
                        <div class="space-y-3">
                            @foreach ($order->fulfillments as $fulfillment)
                                <div class="rounded-lg bg-white ring-1 ring-inset ring-shop-line px-4 py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <x-badge :color="$fulfillment->status_badge" dot>{{ \Illuminate\Support\Str::headline($fulfillment->status) }}</x-badge>
                                        @if ($fulfillment->shipped_at)
                                            <span class="text-xs text-shop-muted">{{ $fulfillment->shipped_at->format('F j, Y') }}</span>
                                        @endif
                                    </div>
                                    @if ($fulfillment->tracking_number)
                                        <p class="mt-2 text-sm text-shop-ink">
                                            {{ $fulfillment->carrier ?: 'Tracking' }}: {{ $fulfillment->tracking_number }}
                                            @if ($fulfillment->tracking_link)
                                                <a href="{{ $fulfillment->tracking_link }}" target="_blank" rel="noopener" class="ml-1 text-brand-700 hover:underline">Track Package</a>
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($order->shipping_address)
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-2">Shipping Address</h2>
                        <address class="not-italic text-sm text-shop-muted leading-relaxed">
                            {{ $order->shipping_address['first_name'] ?? '' }} {{ $order->shipping_address['last_name'] ?? '' }}<br>
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
                <x-card title="Invoice Summary">
                    @if (($payUrl ?? null))
                        <x-button href="{{ $payUrl }}" icon="credit-card" class="w-full mb-4">Pay Now</x-button>
                    @endif
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
                        @if ($order->refunded_cents > 0)
                            <div class="flex justify-between text-rose-600"><dt>Refunded</dt><dd class="tabular">&minus;{{ $order->refunded_formatted }}</dd></div>
                        @endif
                    </dl>
                </x-card>
            </div>
        </div>
    </section>

</x-layouts.shop>
