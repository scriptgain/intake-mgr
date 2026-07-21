<x-layouts.shop :title="$heading">

    {{-- Header band: a soft brand wash with an eyebrow and a large title, so a
         category page reads as a designed landing, not a bare heading. --}}
    <section class="relative isolate overflow-hidden border-b border-shop-line">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-br from-brand-50 via-shop-bg to-shop-bg"></div>
        <div class="pointer-events-none absolute -right-24 -top-28 -z-10 h-80 w-80 rounded-full bg-brand-100/50 blur-3xl"></div>

        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-8">
            <p class="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-700 ring-1 ring-inset ring-brand-200 backdrop-blur">
                <x-icon name="bag" class="w-3.5 h-3.5 shrink-0" /> {{ $collection ? 'Collection' : 'Shop' }}
            </p>
            <h1 class="mt-5 font-display text-4xl sm:text-5xl font-semibold tracking-tight text-shop-ink">{{ $heading }}</h1>
            @if ($subheading)
                <p class="mt-3 max-w-2xl text-lg leading-relaxed text-shop-muted">{{ $subheading }}</p>
            @endif

            {{-- Collection quick links, styled as a pill row inside the band. --}}
            @if ($collections->isNotEmpty())
                <div class="mt-8 flex items-center gap-2 overflow-x-auto no-scrollbar">
                    <a href="{{ route('shop.catalog') }}" @class([
                        'shrink-0 rounded-full px-4 py-2 text-sm font-medium transition ring-1 ring-inset',
                        'bg-brand-600 text-white ring-brand-600 shadow-sm' => ! $collection,
                        'bg-white/80 text-shop-ink ring-shop-line hover:ring-brand-300 hover:text-brand-700 backdrop-blur' => $collection,
                    ])>All Products</a>
                    @foreach ($collections as $navCollection)
                        @php($isActive = $collection && $collection->id === $navCollection->id)
                        <a href="{{ route('shop.collection', $navCollection) }}" @class([
                            'shrink-0 rounded-full px-4 py-2 text-sm font-medium transition ring-1 ring-inset',
                            'bg-brand-600 text-white ring-brand-600 shadow-sm' => $isActive,
                            'bg-white/80 text-shop-ink ring-shop-line hover:ring-brand-300 hover:text-brand-700 backdrop-blur' => ! $isActive,
                        ])>{{ $navCollection->name }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Toolbar: count + sort + price filter --}}
        <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-end justify-between gap-4 mb-8">
            <p class="text-sm text-shop-muted">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('Product', $products->total()) }}</p>
            @if ($filters['q'] ?? null)
                <input type="hidden" name="q" value="{{ $filters['q'] }}">
            @endif
            <div class="flex flex-wrap items-end gap-3">
                <x-field label="Min Price" class="w-28">
                    <x-input type="number" name="min" min="0" step="1" value="{{ $filters['min'] ?? '' }}" placeholder="$0" />
                </x-field>
                <x-field label="Max Price" class="w-28">
                    <x-input type="number" name="max" min="0" step="1" value="{{ $filters['max'] ?? '' }}" placeholder="Any" />
                </x-field>
                <x-field label="Sort By" class="w-52">
                    <x-select name="sort" onchange="this.form.submit()">
                        @foreach ($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </x-field>
                <x-button type="submit" variant="secondary">Apply</x-button>
            </div>
        </form>

        @if ($products->isEmpty())
            <x-empty-state icon="bag" title="No Products Found" description="Try adjusting your filters or search terms.">
                <x-slot:action>
                    <x-button href="{{ route('shop.catalog') }}" variant="secondary">Clear Filters</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-10">
                @foreach ($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>

            <div class="mt-12">
                {{ $products->links() }}
            </div>
        @endif
    </section>

</x-layouts.shop>
