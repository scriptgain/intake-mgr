<x-layouts.shop :title="$product->name">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <nav class="flex items-center gap-2 text-sm text-shop-muted" aria-label="Breadcrumb">
            <a href="{{ route('shop.home') }}" class="hover:text-shop-ink transition">Home</a>
            <x-icon name="chevron-right" class="w-3.5 h-3.5" />
            @if ($product->collections->isNotEmpty())
                <a href="{{ route('shop.collection', $product->collections->first()) }}" class="hover:text-shop-ink transition">{{ $product->collections->first()->name }}</a>
                <x-icon name="chevron-right" class="w-3.5 h-3.5" />
            @endif
            <span class="text-shop-ink font-medium truncate max-w-[16rem]">{{ $product->name }}</span>
        </nav>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16">

            {{-- Gallery --}}
            <div x-data="{ active: 0 }">
                <div class="shop-media rounded-2xl">
                    @forelse ($product->images as $image)
                        <img x-show="active === {{ $loop->index }}" x-cloak src="{{ $image->url }}" alt="{{ $image->alt_text }}">
                    @empty
                        <div class="w-full h-full flex items-center justify-center text-shop-muted">
                            <x-icon name="bag" class="w-16 h-16" />
                        </div>
                    @endforelse
                </div>
                @if ($product->images->count() > 1)
                    <div class="mt-4 grid grid-cols-5 gap-3">
                        @foreach ($product->images as $image)
                            <button type="button" @click="active = {{ $loop->index }}"
                                class="shop-media rounded-lg transition"
                                :class="active === {{ $loop->index }} && 'ring-2 ring-inset ring-brand-600'">
                                <img src="{{ $image->url }}" alt="{{ $image->alt_text }}" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Buy box --}}
            <div x-data="variantPicker({
                    axes: @js($optionAxes),
                    variantMap: @js($variantMap),
                    initial: @js($defaultVariant?->option_values ?? []),
                })" x-init="init()">

                <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">{{ $product->name }}</h1>
                @if ($product->excerpt)
                    <p class="mt-3 text-shop-muted leading-relaxed">{{ $product->excerpt }}</p>
                @endif

                <div class="mt-6 flex items-baseline gap-3">
                    <span class="text-3xl font-semibold text-shop-ink" x-text="price"></span>
                    <span x-show="compareAt" x-cloak class="text-lg text-shop-muted line-through" x-text="compareAt"></span>
                </div>

                <form method="POST" action="{{ route('shop.cart.add') }}" class="mt-6">
                    @csrf
                    <input type="hidden" name="variant_id" x-bind:value="variantId ?? ''">

                    @foreach ($optionAxes as $axis)
                        <div class="mb-6">
                            <p class="text-sm font-medium text-shop-ink mb-2">{{ $axis['name'] }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($axis['values'] as $value)
                                    <button type="button" class="shop-option"
                                        x-bind:class="{ 'is-active': isSelected({{ $axis['index'] }}, @js($value)), 'is-disabled': !isAvailable({{ $axis['index'] }}, @js($value)) }"
                                        @click="isAvailable({{ $axis['index'] }}, @js($value)) && select({{ $axis['index'] }}, @js($value))">{{ $value }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <template x-if="!inStock">
                        <p class="mb-4 text-sm font-medium text-rose-600">Out Of Stock</p>
                    </template>
                    <template x-if="inStock && lowStock">
                        <p class="mb-4 text-sm font-medium text-amber-600">Only <span x-text="qtyLeft"></span> Left In Stock</p>
                    </template>

                    <div class="flex items-stretch gap-3">
                        {{-- Filled segmented control: no border to look dark or vanish
                             on hover; each button gets a white chip on hover. --}}
                        <div class="inline-flex items-center gap-0.5 rounded-lg bg-slate-100 p-1 shrink-0" x-data="quantityStepper(1)">
                            <button type="button" @click="dec()" class="w-9 h-9 flex items-center justify-center rounded-md text-shop-muted hover:bg-white hover:text-shop-ink hover:shadow-sm transition" aria-label="Decrease Quantity">
                                <x-icon name="minus" class="w-4 h-4" />
                            </button>
                            <input type="number" name="quantity" min="1" x-model.number="qty" class="w-12 border-0 bg-transparent text-center text-sm font-medium text-shop-ink focus:ring-0 tabular">
                            <button type="button" @click="inc()" class="w-9 h-9 flex items-center justify-center rounded-md text-shop-muted hover:bg-white hover:text-shop-ink hover:shadow-sm transition" aria-label="Increase Quantity">
                                <x-icon name="plus" class="w-4 h-4" />
                            </button>
                        </div>
                        <x-button type="submit" size="lg" class="flex-1" icon="bag" x-bind:disabled="!inStock">Add To Cart</x-button>
                    </div>

                    <p class="mt-3 text-xs text-shop-muted" x-show="sku" x-cloak x-text="'SKU: ' + sku"></p>
                </form>

                @if ($product->description)
                    <div class="mt-10 pt-8 border-t border-shop-line">
                        <h2 class="text-sm font-semibold text-shop-ink mb-3">Description</h2>
                        <div class="whitespace-pre-line text-shop-muted leading-relaxed">{{ $product->description }}</div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <div class="section-divider"></div>

        <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
            <h2 class="text-2xl font-semibold tracking-tight text-shop-ink mb-10">You May Also Like</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-10">
                @foreach ($related as $relatedProduct)
                    <x-product-card :product="$relatedProduct" />
                @endforeach
            </div>
        </section>
    @endif

</x-layouts.shop>
