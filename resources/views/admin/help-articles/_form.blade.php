<form method="POST" action="{{ $article->exists ? route('help-articles.update', $article) : route('help-articles.store') }}" class="space-y-6">
    @csrf
    @if ($article->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Article">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Title" for="title" required :error="$errors->first('title')" class="sm:col-span-2">
                        <x-input id="title" name="title" data-slug-source="#slug" :value="old('title', $article->title)" required autofocus />
                    </x-field>
                    <x-field label="Slug" for="slug" hint="Blank auto-generates from the title." :error="$errors->first('slug')">
                        <x-input id="slug" name="slug" :value="old('slug', $article->slug)" placeholder="auto-generated" />
                    </x-field>
                    <x-field label="Position" for="position" hint="Lower numbers sort first within the category." :error="$errors->first('position')">
                        <x-input id="position" name="position" type="number" min="0" :value="old('position', $article->position ?? 0)" />
                    </x-field>
                    <x-field label="Excerpt" for="excerpt" hint="A one-line summary shown in listings and search." :error="$errors->first('excerpt')" class="sm:col-span-2">
                        <x-input id="excerpt" name="excerpt" :value="old('excerpt', $article->excerpt)" maxlength="500" />
                    </x-field>
                    <x-field label="Body" for="body" hint="Written in Markdown: # headings, **bold**, - lists, [links](https://example.com)." :error="$errors->first('body')" class="sm:col-span-2">
                        <textarea id="body" name="body" rows="16"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                            placeholder="## Heading&#10;&#10;Write your answer here.">{{ old('body', $article->body) }}</textarea>
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Placement">
                <x-field label="Category" for="help_category_id" required :error="$errors->first('help_category_id')">
                    <x-select id="help_category_id" name="help_category_id">
                        <option value="">Select A Category</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) old('help_category_id', $article->help_category_id) === (string) $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <x-toggle name="is_published" :checked="old('is_published', $article->is_published ?? true)"
                        label="Published" description="Unpublished articles are hidden from the storefront." />
                </div>
                @if ($article->exists && $article->category)
                    <div class="mt-5 border-t border-slate-100 pt-4 text-sm">
                        <a href="{{ route('shop.help.article', [$article->category, $article]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:text-brand-800">View On Storefront <x-icon name="external" class="h-3.5 w-3.5" /></a>
                    </div>
                @endif
            </x-card>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-6">
        <x-button variant="secondary" href="{{ route('help-articles.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $article->exists ? 'Save Changes' : 'Create Article' }}</x-button>
    </div>
</form>
