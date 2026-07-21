<form method="POST" action="{{ $page->exists ? route('store-pages.update', $page) : route('store-pages.store') }}" class="space-y-6">
    @csrf
    @if ($page->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Page">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Title" for="title" required :error="$errors->first('title')" class="sm:col-span-2">
                        <x-input id="title" name="title" data-slug-source="#slug" :value="old('title', $page->title)" required autofocus />
                    </x-field>
                    <x-field label="Slug" for="slug" hint="Reached at /pages/{slug}. Blank auto-generates." :error="$errors->first('slug')">
                        <x-input id="slug" name="slug" :value="old('slug', $page->slug)" placeholder="auto-generated" />
                    </x-field>
                    <x-field label="Position" for="position" hint="Lower numbers sort first in the admin list." :error="$errors->first('position')">
                        <x-input id="position" name="position" type="number" min="0" :value="old('position', $page->position ?? 0)" />
                    </x-field>
                    <x-field label="Body" for="body" hint="Written in Markdown: # headings, **bold**, - lists, [links](https://example.com)." :error="$errors->first('body')" class="sm:col-span-2">
                        <textarea id="body" name="body" rows="20"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                            placeholder="## Heading&#10;&#10;Write your policy here.">{{ old('body', $page->body) }}</textarea>
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Visibility">
                <x-toggle name="is_published" :checked="old('is_published', $page->is_published ?? true)"
                    label="Published" description="Unpublished pages return 404 on the storefront." />
                @if ($page->exists)
                    <div class="mt-5 border-t border-slate-100 pt-4 text-sm">
                        <a href="{{ route('shop.page', $page) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:text-brand-800">View On Storefront <x-icon name="external" class="h-3.5 w-3.5" /></a>
                    </div>
                @endif
            </x-card>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-6">
        <x-button variant="secondary" href="{{ route('store-pages.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $page->exists ? 'Save Changes' : 'Create Page' }}</x-button>
    </div>
</form>
