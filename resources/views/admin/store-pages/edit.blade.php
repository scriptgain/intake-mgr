<x-layouts.app title="Edit Page">
    <x-page-header title="Edit Page" icon="info" :subtitle="$page->title">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('store-pages.index') }}" icon="chevron-left">Back To Pages</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.store-pages._form')
</x-layouts.app>
