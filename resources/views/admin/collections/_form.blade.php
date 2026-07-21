@php $isEdit = $collection->exists; @endphp
<form method="POST" action="{{ $isEdit ? route('collections.update', $collection) : route('collections.store') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="min-w-0 lg:col-span-2 space-y-6">
            <x-card title="Details">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Name" for="name" required :error="$errors->first('name')" class="sm:col-span-2">
                        <x-input id="name" name="name" data-slug-source="#slug" :value="old('name', $collection->name)" required autofocus />
                    </x-field>
                    <x-field label="Slug" for="slug" hint="Blank auto-generates from the name." :error="$errors->first('slug')">
                        <x-input id="slug" name="slug" :value="old('slug', $collection->slug)" placeholder="auto-generated" />
                    </x-field>
                    <x-field label="Position" for="position" hint="Lower numbers sort first." :error="$errors->first('position')">
                        <x-input id="position" name="position" type="number" min="0" :value="old('position', $collection->position ?? 0)" />
                    </x-field>
                    <x-field label="Description" for="description" :error="$errors->first('description')" class="sm:col-span-2">
                        <textarea id="description" name="description" rows="5"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('description', $collection->description) }}</textarea>
                    </x-field>
                </div>
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <x-toggle name="is_active" :checked="old('is_active', $collection->is_active ?? true)"
                        label="Active" description="Inactive collections are hidden from the storefront." />
                </div>
            </x-card>

            <x-seo-panel :entity="$collection" />

            <x-card title="Products" subtitle="Products included in this collection.">
                @if ($products->isEmpty())
                    <x-empty-state icon="bag" title="No Products Yet" description="Create products first, then add them here." />
                @else
                    <div class="max-h-96 overflow-y-auto vx-scroll grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        @foreach ($products as $product)
                            <div class="rounded-lg ring-1 ring-slate-200 px-3 py-2.5 hover:bg-slate-50 transition">
                                <x-check-switch name="products[]" :value="$product->id"
                                    :checked="in_array($product->id, old('products', $selectedProducts))">
                                    <span class="truncate">{{ $product->name }}</span>
                                </x-check-switch>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Image">
                <div x-data="imagePreview()" class="space-y-3">
                    <div class="aspect-square rounded-lg bg-slate-100 ring-1 ring-slate-200 overflow-hidden flex items-center justify-center text-slate-300">
                        <template x-if="preview">
                            <img :src="preview" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!preview">
                            @if ($collection->image_url)
                                <img src="{{ $collection->image_url }}" alt="{{ $collection->name }}" class="w-full h-full object-cover">
                            @else
                                <x-icon name="folder" class="w-8 h-8" />
                            @endif
                        </template>
                    </div>
                    <input type="file" name="image" accept="image/*" @change="onChange"
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                </div>
            </x-card>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-6">
        <x-button variant="secondary" href="{{ route('collections.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $isEdit ? 'Save Changes' : 'Create Collection' }}</x-button>
    </div>
</form>
