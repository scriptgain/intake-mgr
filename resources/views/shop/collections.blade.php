<x-layouts.shop title="Collections">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-10">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">Collections</h1>
        <p class="mt-2 text-shop-muted max-w-2xl">Browse the shop by collection.</p>
    </section>

    <div class="section-divider"></div>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if ($collections->isEmpty())
            <x-empty-state icon="folder" title="No Collections Yet" description="Check back soon." />
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
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
        @endif
    </section>

</x-layouts.shop>
