<x-layouts.app title="Edit Collection">
    <x-page-header title="Edit Collection" icon="folder" :subtitle="$collection->name">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('collections.index') }}" icon="chevron-left">Back To Collections</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.collections._form')
</x-layouts.app>
