<x-layouts.app title="New Article">
    <x-page-header title="New Article" icon="book" subtitle="Write a single question and its answer.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('help-articles.index') }}" icon="chevron-left">Back To Articles</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.help-articles._form')
</x-layouts.app>
