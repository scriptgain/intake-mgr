<x-layouts.app title="Edit Article">
    <x-page-header title="Edit Article" icon="book" :subtitle="$article->title">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('help-articles.index') }}" icon="chevron-left">Back To Articles</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.help-articles._form')
</x-layouts.app>
