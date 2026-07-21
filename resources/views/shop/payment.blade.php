<x-layouts.shop title="Payment">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">Payment</h1>
        <p class="mt-2 text-sm text-shop-muted">Order {{ $order->number }}. Your order is reserved and will be confirmed once payment completes.</p>
    </section>

    <div class="section-divider"></div>

    @if ($isTestMode)
        {{-- Unmistakable, not a subtle badge: a store left in test mode by
             accident takes no money, and the shopper must be able to tell. --}}
        <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-6">
            <div class="flex items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-inset ring-amber-200">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-700 ring-1 ring-amber-200 shrink-0">
                    <x-icon name="warning" class="w-4 h-4" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-amber-900">Test Mode</p>
                    <p class="text-sm text-amber-800">This store is running in Stripe test mode. No real payment will be taken and no card will be charged. Use card number 4242 4242 4242 4242 with any future expiry and any CVC.</p>
                </div>
            </div>
        </section>
    @endif

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

            {{-- Card form --}}
            <div class="min-w-0 lg:col-span-2">
                @if (! $clientSecret)
                    <x-alert type="danger" title="Payment Unavailable">
                        {{ $paymentError ?: 'We could not start a card payment for this order.' }}
                    </x-alert>
                    <div class="mt-6">
                        <x-button href="{{ route('shop.cart') }}" variant="secondary">
                            <x-icon name="chevron-left" class="w-4 h-4" />
                            Back To Cart
                        </x-button>
                    </div>
                @else
                    <div x-data="stripePayment({
                            publishableKey: @js($publishableKey),
                            clientSecret: @js($clientSecret),
                            returnUrl: @js($returnUrl),
                            accent: @js($accent),
                            initialError: @js($paymentError),
                         })">

                        <h2 class="text-lg font-semibold text-shop-ink mb-4">Card Details</h2>

                        {{-- Stripe mounts its iframe here. Card data is entered
                             inside Stripe's own frame and never touches this
                             page's DOM, this server, or these logs. --}}
                        <div x-ref="paymentElement" class="min-h-[12rem]"></div>

                        <div x-show="!ready && !error" x-cloak class="flex items-center gap-2 text-sm text-shop-muted">
                            <x-icon name="refresh" class="w-4 h-4 animate-spin" />
                            <span>Loading Secure Card Form</span>
                        </div>

                        <template x-if="error">
                            <div class="mt-4 flex items-start gap-3 rounded-xl bg-rose-50 px-4 py-3 ring-1 ring-inset ring-rose-200">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-rose-100 text-rose-700 ring-1 ring-rose-200 shrink-0">
                                    <x-icon name="warning" class="w-4 h-4" />
                                </span>
                                <p class="text-sm text-rose-800 min-w-0" x-text="error"></p>
                            </div>
                        </template>

                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <x-button type="button" size="lg" x-on:click="pay()"
                                      x-bind:disabled="processing || !ready"
                                      class="justify-center">
                                <x-icon name="lock" class="w-4 h-4" />
                                <span x-show="!processing">Pay {{ $order->total_formatted }}</span>
                                <span x-show="processing" x-cloak>Processing</span>
                            </x-button>

                            <p class="text-xs text-shop-muted inline-flex items-center gap-1.5">
                                <x-icon name="lock" class="w-3.5 h-3.5 shrink-0" />
                                Payments Are Processed By Stripe. We Never See Your Card Number.
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Order summary --}}
            <div>
                <x-card title="Order Summary" class="lg:sticky lg:top-24">
                    <ul class="space-y-4 mb-6">
                        @foreach ($order->items as $item)
                            <li class="flex items-center gap-3">
                                <span class="shop-media w-14 h-14 rounded-lg shrink-0 relative">
                                    @if ($item->image_url)
                                        <img src="{{ $item->image_url }}" alt="{{ $item->name }}">
                                    @endif
                                    <span class="absolute -top-2 -right-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 rounded-full bg-slate-700 text-white text-[11px] font-semibold px-1">{{ $item->quantity }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-shop-ink truncate">{{ $item->name }}</span>
                                    @if ($item->variant_label)
                                        <span class="block text-xs text-shop-muted">{{ $item->variant_label }}</span>
                                    @endif
                                </span>
                                <span class="text-sm text-shop-ink tabular shrink-0">{{ $item->total_formatted }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="space-y-3 text-sm border-t border-shop-line pt-4">
                        <div class="flex justify-between"><dt class="text-shop-muted">Subtotal</dt><dd class="tabular text-shop-ink">{{ $order->subtotal_formatted }}</dd></div>
                        @if ($order->discount_cents > 0)
                            <div class="flex justify-between"><dt class="text-shop-muted">Discount</dt><dd class="tabular text-emerald-600">-{{ $order->discount_formatted }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-shop-muted">Shipping</dt><dd class="tabular text-shop-ink">{{ $order->shipping_formatted }}</dd></div>
                        <div class="flex justify-between"><dt class="text-shop-muted">Tax</dt><dd class="tabular text-shop-ink">{{ $order->tax_formatted }}</dd></div>
                        <div class="pt-3 border-t border-shop-line flex justify-between text-base font-semibold">
                            <dt class="text-shop-ink">Total</dt><dd class="tabular text-shop-ink">{{ $order->total_formatted }}</dd>
                        </div>
                    </dl>

                    @if ($order->shipping_method)
                        <p class="mt-4 text-xs text-shop-muted">Shipping Method: {{ $order->shipping_method }}</p>
                    @endif
                </x-card>
            </div>
        </div>
    </section>

</x-layouts.shop>
