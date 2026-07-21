<x-layouts.app title="New Category">
    <x-page-header title="New Category" icon="folder" subtitle="Group related help articles under one topic.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('help-categories.index') }}" icon="chevron-left">Back To Categories</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.help-categories._form')
</x-layouts.app>
