<x-layouts.app title="Edit Product">
    <x-page-header
        eyebrow="Catalog"
        :title="$product->name"
        icon="bag"
        subtitle="Edit this product, its variants, stock, and how it is listed."
        :back="['href' => route('products.index'), 'label' => 'All Products']">
        <x-slot:meta>
            <x-badge :color="$product->status_badge" dot>{{ \Illuminate\Support\Str::headline($product->status) }}</x-badge>
            <span class="text-xs text-slate-500">{{ $product->variants->count() }} {{ \Illuminate\Support\Str::plural('Variant', $product->variants->count()) }}</span>
        </x-slot:meta>
        <x-slot:actions>
            @if ($product->status === 'active')
                <x-button variant="secondary" size="sm" icon="external"
                    href="{{ route('shop.product', $product->slug) }}" target="_blank" rel="noopener">View On Storefront</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @include('admin.products._form')
</x-layouts.app>
