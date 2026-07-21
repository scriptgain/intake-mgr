<x-layouts.app title="Edit Category">
    <x-page-header title="Edit Category" icon="folder" :subtitle="$category->name">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('help-categories.index') }}" icon="chevron-left">Back To Categories</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.help-categories._form')
</x-layouts.app>
