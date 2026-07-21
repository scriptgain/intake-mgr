<x-layouts.app title="New Product">
    <x-page-header
        eyebrow="Catalog"
        title="New Product"
        icon="bag"
        subtitle="Fill in the General tab, add a variant with its price, then save."
        :back="['href' => route('products.index'), 'label' => 'All Products']" />

    @include('admin.products._form')
</x-layouts.app>
