<x-layouts.app title="New Collection">
    <x-page-header title="New Collection" icon="folder" subtitle="Group related products for the storefront.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('collections.index') }}" icon="chevron-left">Back To Collections</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.collections._form')
</x-layouts.app>
