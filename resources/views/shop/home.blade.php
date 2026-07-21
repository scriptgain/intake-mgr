<x-layouts.shop>

    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-white">
        {{-- Soft brand wash, kept behind content so type stays crisp --}}
        <div class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-br from-brand-50 via-white to-white"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 -z-10 h-96 w-96 rounded-full bg-brand-100/50 blur-3xl"></div>

        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24">
            <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-16">

                <div class="min-w-0 lg:col-span-7">
                    <p class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-700 ring-1 ring-inset ring-brand-200">
                        {{ config('shop.store_tagline') }}
                    </p>

                    <h1 class="mt-6 font-display text-4xl sm:text-5xl lg:text-6xl font-semibold leading-[1.03] tracking-tight text-shop-ink">
                        Goods Worth<br class="hidden sm:block"> Living With.
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-shop-muted">
                        A carefully edited selection, made to last and shipped straight to your door.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-3">
                        <x-button href="{{ route('shop.catalog') }}" size="lg">Shop All Products</x-button>
                        <a href="{{ route('shop.collections') }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-shop-ink ring-1 ring-inset ring-shop-line transition hover:bg-slate-50">
                            Browse Collections
                            <x-icon name="chevron-right" class="w-4 h-4 shrink-0 text-slate-400" />
                        </a>
                    </div>

                    {{-- The three objections a first-time buyer actually has.
                         flex-wrap wraps whole items, never the text. --}}
                    <dl class="mt-10 flex flex-wrap gap-x-8 gap-y-4">
                        <div class="flex items-start gap-2.5">
                            <x-icon name="truck" class="mt-0.5 w-4 h-4 shrink-0 text-brand-600" />
                            <div>
                                <dt class="text-sm font-semibold text-shop-ink whitespace-nowrap">Free Shipping</dt>
                                <dd class="text-xs text-shop-muted whitespace-nowrap">On orders over $75</dd>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <x-icon name="shield" class="mt-0.5 w-4 h-4 shrink-0 text-brand-600" />
                            <div>
                                <dt class="text-sm font-semibold text-shop-ink whitespace-nowrap">Two-Year Guarantee</dt>
                                <dd class="text-xs text-shop-muted whitespace-nowrap">On everything we sell</dd>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <x-icon name="refresh" class="mt-0.5 w-4 h-4 shrink-0 text-brand-600" />
                            <div>
                                <dt class="text-sm font-semibold text-shop-ink whitespace-nowrap">Easy Returns</dt>
                                <dd class="text-xs text-shop-muted whitespace-nowrap">30 days, no fuss</dd>
                            </div>
                        </div>
                    </dl>
                </div>

                {{-- A shop hero should sell a product, not just describe a mood. --}}
                @if ($featured->isNotEmpty())
                    @php($heroProduct = $featured->first())
                    <div class="min-w-0 lg:col-span-5">
                        <a href="{{ route('shop.product', $heroProduct) }}"
                           class="group block overflow-hidden rounded-2xl bg-white ring-1 ring-shop-line shadow-sm transition hover:shadow-md">
                            <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
                                @if ($heroProduct->images->isNotEmpty())
                                    <img src="{{ $heroProduct->images->first()->url }}" alt="{{ $heroProduct->name }}"
                                         class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
                                @else
                                    <span class="flex h-full w-full items-center justify-center text-slate-300">
                                        <x-icon name="bag" class="w-16 h-16" />
                                    </span>
                                @endif
                                <span class="absolute left-4 top-4 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-700 ring-1 ring-inset ring-brand-200 backdrop-blur">
                                    Featured
                                </span>
                            </div>
                            <div class="flex items-end justify-between gap-4 p-5">
                                <div class="min-w-0">
                                    <h2 class="font-semibold text-shop-ink transition group-hover:text-brand-700">{{ $heroProduct->name }}</h2>
                                    @if ($heroProduct->excerpt)
                                        <p class="mt-1 line-clamp-2 text-sm text-shop-muted">{{ $heroProduct->excerpt }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 text-right">
                                    @if ($heroProduct->has_price_range)
                                        <span class="block text-[11px] uppercase tracking-widest text-shop-muted">From</span>
                                    @endif
                                    <span class="block text-lg font-semibold text-shop-ink">{{ $heroProduct->price_from_formatted }}</span>
                                </span>
                            </div>
                        </a>
                    </div>
                @endif

            </div>
        </div>
    </section>

    <div class="section-divider"></div>

    {{-- Featured products --}}
    @if ($featured->isNotEmpty())
        <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
            <div class="flex items-end justify-between gap-4 mb-10">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-shop-ink">Featured</h2>
                    <p class="mt-1 text-shop-muted">Hand-picked pieces from the current collection.</p>
                </div>
                <a href="{{ route('shop.catalog') }}" class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800 transition shrink-0">
                    View All <x-icon name="chevron-right" class="w-4 h-4" />
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-10">
                @foreach ($featured as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>

        <div class="section-divider"></div>
    @endif

    {{-- Collections strip --}}
    @if ($collections->isNotEmpty())
        <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
            <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-shop-ink mb-10">Shop By Collection</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @foreach ($collections as $collection)
                    <a href="{{ route('shop.collection', $collection) }}" class="group relative block overflow-hidden rounded-2xl shop-media" style="aspect-ratio: 4 / 5;">
                        @if ($collection->image_url)
                            <img src="{{ $collection->image_url }}" alt="{{ $collection->name }}" loading="lazy">
                        @else
                            <div class="w-full h-full bg-gradient-to-br from-brand-100 to-brand-50"></div>
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/70 via-slate-900/10 to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-5">
                            <p class="text-lg font-semibold text-white">{{ $collection->name }}</p>
                            <p class="text-sm text-white/80">{{ $collection->products_count }} {{ \Illuminate\Support\Str::plural('Item', $collection->products_count) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="section-divider"></div>
    @endif

    {{-- New arrivals --}}
    @if ($newest->isNotEmpty())
        <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
            <div class="flex items-end justify-between gap-4 mb-10">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-shop-ink">New Arrivals</h2>
                    <p class="mt-1 text-shop-muted">Just landed in the shop.</p>
                </div>
                <a href="{{ route('shop.catalog', ['sort' => 'newest']) }}" class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800 transition shrink-0">
                    View All <x-icon name="chevron-right" class="w-4 h-4" />
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-10">
                @foreach ($newest as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

</x-layouts.shop>
