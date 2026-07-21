<x-layouts.shop title="Your Cart">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">Your Cart</h1>
    </section>

    <div class="section-divider"></div>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-10">

        @if (! empty($notices))
            <div class="space-y-3 mb-8">
                @foreach ($notices as $notice)
                    <x-alert type="warn">{{ $notice }}</x-alert>
                @endforeach
            </div>
        @endif

        @if ($cart->is_empty)
            {{-- Centered empty state: the one place a centered icon is right, per
                 e-commerce convention and Allen's explicit ask. --}}
            <div class="mx-auto max-w-md py-16 text-center sm:py-24">
                <span class="mx-auto inline-flex h-20 w-20 items-center justify-center rounded-full bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
                    <x-icon name="bag" class="w-9 h-9" />
                </span>
                <h2 class="mt-6 text-2xl font-semibold tracking-tight text-shop-ink">Your Cart Is Empty</h2>
                <p class="mx-auto mt-2 max-w-sm text-shop-muted leading-relaxed">Looks like you have not added anything yet. Browse the shop and find something you love.</p>
                <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <x-button href="{{ route('shop.catalog') }}" size="lg" icon="bag">Start Shopping</x-button>
                    <a href="{{ route('shop.collections') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800 transition">Browse Collections</a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

                {{-- Line items: one connected white card, items split by a light divider. --}}
                <div class="min-w-0 lg:col-span-2 overflow-hidden rounded-xl bg-white ring-1 ring-inset ring-shop-line divide-y divide-shop-line">
                    @foreach ($cart->items as $item)
                        @php $image = $item->product?->primaryImage(); @endphp
                        <div class="flex gap-4 sm:gap-6 p-4 sm:p-5">
                            <a href="{{ $item->product ? route('shop.product', $item->product) : '#' }}" class="shop-media w-24 h-24 sm:w-28 sm:h-28 rounded-xl shrink-0">
                                @if ($image)
                                    <img src="{{ $image->url }}" alt="{{ $image->alt_text }}">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-shop-muted">
                                        <x-icon name="bag" class="w-8 h-8" />
                                    </div>
                                @endif
                            </a>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <a href="{{ $item->product ? route('shop.product', $item->product) : '#' }}" class="font-medium text-shop-ink hover:text-brand-700 transition truncate block">
                                            {{ $item->product?->name ?? 'Unavailable Product' }}
                                        </a>
                                        @if ($item->variant && $item->variant->name !== 'Default')
                                            <p class="mt-0.5 text-sm text-shop-muted">{{ $item->variant->name }}</p>
                                        @endif
                                        <p class="mt-0.5 text-sm text-shop-muted">{{ $item->unit_price_formatted }} Each</p>

                                        @if ($item->is_overstocked)
                                            <p class="mt-1.5 text-xs font-medium text-amber-600">Only {{ $item->max_quantity }} Left. Quantity Reduced At Checkout.</p>
                                        @endif
                                        @if ($item->is_repriced)
                                            <p class="mt-1.5 text-xs font-medium text-brand-600">Price Updated Since Added</p>
                                        @endif
                                    </div>
                                    <p class="font-semibold text-shop-ink shrink-0 tabular">{{ $item->line_total_formatted }}</p>
                                </div>

                                <div class="mt-3 flex items-center gap-3">
                                    <form method="POST" action="{{ route('shop.cart.update', $item) }}" class="flex items-center gap-2" x-data="quantityStepper({{ $item->quantity }})">
                                        @csrf
                                        @method('PATCH')
                                        {{-- overflow-hidden clips each button's hover fill to the
                                             rounded corners so it cannot bleed over the ring border. --}}
                                        {{-- Filled segmented control: no border to look dark or
                                             vanish on hover; each button gets a white chip on hover. --}}
                                        <div class="inline-flex items-center gap-0.5 rounded-lg bg-slate-100 p-1">
                                            <button type="button" @click="dec()" class="w-8 h-8 flex items-center justify-center rounded-md text-shop-muted hover:bg-white hover:text-shop-ink hover:shadow-sm transition" aria-label="Decrease Quantity">
                                                <x-icon name="minus" class="w-3.5 h-3.5" />
                                            </button>
                                            <input type="number" name="quantity" min="1" x-model.number="qty" class="w-9 border-0 bg-transparent text-center text-sm font-medium text-shop-ink focus:ring-0 tabular">
                                            <button type="button" @click="inc()" class="w-8 h-8 flex items-center justify-center rounded-md text-shop-muted hover:bg-white hover:text-shop-ink hover:shadow-sm transition" aria-label="Increase Quantity">
                                                <x-icon name="plus" class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                        <button type="submit" class="text-sm font-medium text-brand-700 hover:text-brand-800 transition">Update</button>
                                    </form>

                                    <x-confirm-action
                                        :name="'remove-item-' . $item->id"
                                        :action="route('shop.cart.remove', $item)"
                                        method="DELETE"
                                        title="Remove Item?"
                                        :message="'Remove ' . ($item->product?->name ?? 'this item') . ' from your cart?'"
                                        confirm="Remove"
                                        confirmVariant="danger"
                                        tone="danger">
                                        <button type="button" class="text-sm font-medium text-shop-muted hover:text-rose-600 transition">Remove</button>
                                    </x-confirm-action>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Order summary --}}
                <div>
                    <x-card title="Order Summary">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-shop-muted">Subtotal</dt>
                                <dd class="tabular text-shop-ink">{{ $quote['formatted']['subtotal'] }}</dd>
                            </div>
                            @if ($quote['discount_cents'] > 0)
                                <div class="flex justify-between">
                                    <dt class="text-shop-muted">Discount</dt>
                                    <dd class="tabular text-emerald-600">&minus;{{ $quote['formatted']['discount'] }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <dt class="text-shop-muted">Shipping</dt>
                                <dd class="tabular text-shop-ink">{{ $quote['formatted']['shipping'] }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-shop-muted">Tax</dt>
                                <dd class="tabular text-shop-ink">{{ $quote['formatted']['tax'] }}</dd>
                            </div>
                            <div class="pt-3 border-t border-shop-line flex justify-between text-base font-semibold">
                                <dt class="text-shop-ink">Total</dt>
                                <dd class="tabular text-shop-ink">{{ $quote['formatted']['total'] }}</dd>
                            </div>
                        </dl>

                        <div class="mt-6 pt-6 border-t border-shop-line">
                            @if ($quote['discount'])
                                <div class="flex items-center justify-between gap-3 rounded-lg bg-emerald-50 ring-1 ring-inset ring-emerald-200 px-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-emerald-800 truncate">{{ $quote['discount']->code }}</p>
                                        <p class="text-xs text-emerald-700">{{ $quote['discount']->value_label }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('shop.cart.discount.remove') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-emerald-700 hover:text-emerald-900 transition shrink-0">Remove</button>
                                    </form>
                                </div>
                            @else
                                <form method="POST" action="{{ route('shop.cart.discount') }}" class="flex gap-2">
                                    @csrf
                                    <x-input type="text" name="code" placeholder="Discount Code" class="flex-1" />
                                    <x-button type="submit" variant="secondary">Apply</x-button>
                                </form>
                            @endif
                        </div>

                        <x-slot:footer>
                            <x-button href="{{ route('shop.checkout') }}" size="lg" class="w-full justify-center">Proceed To Checkout</x-button>
                        </x-slot:footer>
                    </x-card>

                    <a href="{{ route('shop.catalog') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-shop-muted hover:text-shop-ink transition">
                        <x-icon name="chevron-left" class="w-4 h-4" /> Continue Shopping
                    </a>
                </div>
            </div>
        @endif
    </section>

</x-layouts.shop>
