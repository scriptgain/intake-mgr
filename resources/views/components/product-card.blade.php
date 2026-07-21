@props(['product'])
@php $image = $product->primaryImage(); @endphp
<a href="{{ route('shop.product', $product) }}" class="group shop-card block">
    <div class="shop-media rounded-xl relative">
        @if ($image)
            <img src="{{ $image->url }}" alt="{{ $image->alt_text }}" loading="lazy">
        @else
            <div class="w-full h-full flex items-center justify-center text-shop-muted">
                <x-icon name="bag" class="w-10 h-10" />
            </div>
        @endif
        @if ($product->is_on_sale)
            <span class="absolute top-3 left-3 inline-flex items-center rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-rose-600 ring-1 ring-inset ring-rose-200">Sale</span>
        @endif
        @if (! $product->is_in_stock)
            <span class="absolute top-3 right-3 inline-flex items-center rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-shop-muted ring-1 ring-inset ring-shop-line">Sold Out</span>
        @endif
    </div>
    <div class="mt-4">
        <h3 class="text-[15px] font-medium text-shop-ink leading-snug group-hover:text-brand-700 transition">{{ $product->name }}</h3>
        <p class="mt-1 text-sm text-shop-muted">
            @if ($product->has_price_range)
                From {{ $product->price_from_formatted }}
            @else
                {{ $product->price_from_formatted }}
            @endif
        </p>
    </div>
</a>
