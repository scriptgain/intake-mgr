<form method="POST" action="{{ $category->exists ? route('help-categories.update', $category) : route('help-categories.store') }}" class="space-y-6">
    @csrf
    @if ($category->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Details">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Name" for="name" required :error="$errors->first('name')" class="sm:col-span-2">
                        <x-input id="name" name="name" data-slug-source="#slug" :value="old('name', $category->name)" required autofocus />
                    </x-field>
                    <x-field label="Slug" for="slug" hint="Blank auto-generates from the name." :error="$errors->first('slug')">
                        <x-input id="slug" name="slug" :value="old('slug', $category->slug)" placeholder="auto-generated" />
                    </x-field>
                    <x-field label="Position" for="position" hint="Lower numbers sort first." :error="$errors->first('position')">
                        <x-input id="position" name="position" type="number" min="0" :value="old('position', $category->position ?? 0)" />
                    </x-field>
                    <x-field label="Icon" for="icon" hint="Shown beside the topic in the Help Center." :error="$errors->first('icon')">
                        <x-select id="icon" name="icon">
                            @foreach ($iconChoices as $choice)
                                <option value="{{ $choice }}" @selected(old('icon', $category->icon ?? 'book') === $choice)>{{ \Illuminate\Support\Str::of($choice)->replace('-', ' ')->title() }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Description" for="description" hint="A short line shown under the topic name." :error="$errors->first('description')" class="sm:col-span-2">
                        <x-input id="description" name="description" :value="old('description', $category->description)" maxlength="500" />
                    </x-field>
                </div>
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <x-toggle name="is_published" :checked="old('is_published', $category->is_published ?? true)"
                        label="Published" description="Unpublished categories are hidden from the storefront Help Center." />
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="At A Glance">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Articles</dt>
                        <dd class="tabular font-medium text-slate-900">{{ $category->articles_count ?? ($category->exists ? $category->articles()->count() : 0) }}</dd>
                    </div>
                    @if ($category->exists)
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500">Public Link</dt>
                            <dd><a href="{{ route('shop.help.category', $category) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:text-brand-800">View <x-icon name="external" class="h-3.5 w-3.5" /></a></dd>
                        </div>
                    @endif
                </dl>
                @if ($category->exists)
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <x-button variant="secondary" size="sm" icon="plus" href="{{ route('help-articles.create', ['category' => $category->id]) }}">Add Article Here</x-button>
                    </div>
                @endif
            </x-card>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-6">
        <x-button variant="secondary" href="{{ route('help-categories.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $category->exists ? 'Save Changes' : 'Create Category' }}</x-button>
    </div>
</form>
