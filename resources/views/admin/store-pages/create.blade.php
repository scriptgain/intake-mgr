<x-layouts.app title="New Page">
    <x-page-header title="New Page" icon="info" subtitle="Add a standalone policy or information page.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('store-pages.index') }}" icon="chevron-left">Back To Pages</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.store-pages._form')
</x-layouts.app>
