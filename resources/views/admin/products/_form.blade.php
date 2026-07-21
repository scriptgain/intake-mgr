{{-- Product editor. Six sections behind tabs rather than one long scroll, since
     a merchant editing copy is almost never also editing stock. --}}
<div x-data="{ tab: 'general' }">
    <x-segmented label="Product Sections" class="mb-6">
        <button type="button" role="tab" :aria-selected="(tab === 'general').toString()" @click="tab = 'general'"
            class="vx-seg-item" :class="tab === 'general' && 'is-active'">General</button>
        <button type="button" role="tab" :aria-selected="(tab === 'variants').toString()" @click="tab = 'variants'"
            class="vx-seg-item" :class="tab === 'variants' && 'is-active'">Variants &amp; Pricing</button>
        <button type="button" role="tab" :aria-selected="(tab === 'inventory').toString()" @click="tab = 'inventory'"
            class="vx-seg-item" :class="tab === 'inventory' && 'is-active'">Inventory</button>
        <button type="button" role="tab" :aria-selected="(tab === 'images').toString()" @click="tab = 'images'"
            class="vx-seg-item" :class="tab === 'images' && 'is-active'">Images</button>
        <button type="button" role="tab" :aria-selected="(tab === 'organization').toString()" @click="tab = 'organization'"
            class="vx-seg-item" :class="tab === 'organization' && 'is-active'">Organization</button>
        <button type="button" role="tab" :aria-selected="(tab === 'seo').toString()" @click="tab = 'seo'"
            class="vx-seg-item" :class="tab === 'seo' && 'is-active'">SEO</button>
    </x-segmented>

    {{-- Name, variants, organization and SEO all save together through this one form. --}}
    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        {{-- General --}}
        <div x-show="tab === 'general'" x-cloak class="space-y-6">
            <x-card title="General" subtitle="What shoppers read first.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Name" for="name" required :error="$errors->first('name')" class="sm:col-span-2">
                        <x-input id="name" name="name" data-slug-source="#slug" :value="old('name', $product->name)" required autofocus />
                    </x-field>
                    <x-field label="Slug" for="slug" hint="Leave blank to build it from the name." :error="$errors->first('slug')">
                        <x-input id="slug" name="slug" :value="old('slug', $product->slug)" placeholder="auto-generated" />
                    </x-field>
                    <x-field label="Status" for="status" required hint="Only Active products appear on the storefront." :error="$errors->first('status')">
                        <x-select id="status" name="status">
                            @foreach (\App\Models\Product::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $product->status) === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Vendor" for="vendor" :error="$errors->first('vendor')">
                        <x-input id="vendor" name="vendor" :value="old('vendor', $product->vendor)" />
                    </x-field>
                    <x-field label="Product Type" for="product_type" :error="$errors->first('product_type')">
                        <x-input id="product_type" name="product_type" :value="old('product_type', $product->product_type)" />
                    </x-field>
                    <x-field label="Tax Class" for="tax_class" hint="Must match the tax class named on a Tax Rule." :error="$errors->first('tax_class')" class="sm:col-span-2">
                        <x-input id="tax_class" name="tax_class" :value="old('tax_class', $product->tax_class ?? 'standard')" placeholder="standard" />
                    </x-field>
                    <x-field label="Excerpt" for="excerpt" hint="One or two lines, shown in product listings." :error="$errors->first('excerpt')" class="sm:col-span-2">
                        <x-input id="excerpt" name="excerpt" :value="old('excerpt', $product->excerpt)" maxlength="500" />
                    </x-field>
                    <x-field label="Description" for="description" :error="$errors->first('description')" class="sm:col-span-2">
                        <textarea id="description" name="description" rows="6"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('description', $product->description) }}</textarea>
                    </x-field>
                </div>
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <x-toggle name="is_featured" :checked="old('is_featured', $product->is_featured)"
                        label="Featured Product" description="Featured products can be highlighted on the storefront home page." />
                </div>
            </x-card>
        </div>

        {{-- Variants & Pricing --}}
        <div x-show="tab === 'variants'" x-cloak>
            <x-card title="Variants & Pricing"
                subtitle="Every product has at least one variant. The variant carries the price, SKU, and stock.">
                <div x-data="variantRepeater(@js($initialVariants))" class="space-y-3">
                    {{-- Each variant is a collapsed summary that opens to edit. A
                         product with eight variants was previously eight stacked
                         blocks of fourteen inputs, which is unusable. --}}
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="overflow-hidden rounded-lg ring-1 ring-slate-200" x-data="{ open: false }">
                            <input type="hidden" :name="`variants[${index}][id]`" :value="row.id">

                            <div class="flex items-center gap-3 bg-slate-50/70 px-4 py-2.5">
                                <button type="button" @click="open = ! open" :aria-expanded="open.toString()"
                                    class="flex min-w-0 flex-1 items-center gap-2.5 text-left">
                                    <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition-transform" ::class="open && 'rotate-90'" aria-hidden="true" />
                                    <span class="truncate text-sm font-semibold text-slate-900" x-text="rowLabel(row, index)"></span>
                                </button>
                                <span class="tabular hidden shrink-0 text-xs text-slate-500 sm:inline" x-text="row.sku || 'No SKU'"></span>
                                <span class="tabular shrink-0 text-sm font-medium text-slate-900" x-text="row.price ? '{{ config('shop.currency_symbol', '$') }}' + row.price : 'No price'"></span>
                                <button type="button" x-show="rows.length > 1" @click="removeRow(index)"
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-rose-600 ring-1 ring-inset ring-rose-200 transition hover:bg-rose-50"
                                    :aria-label="`Remove ${rowLabel(row, index)}`">
                                    <x-icon name="trash" class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </div>

                            <div x-show="open" x-cloak class="space-y-4 border-t border-slate-200 p-4">
                                <div>
                                    <p class="vx-eyebrow mb-2.5">Options</p>
                                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                        {{-- Placeholders only on the first axis. Showing
                                             "Color"/"Charcoal" as hints on axes 2 and 3 read as
                                             real saved values at a glance. --}}
                                        <template x-for="n in [1, 2, 3]" :key="n">
                                            <div class="space-y-3">
                                                <div class="space-y-1.5">
                                                    <label class="block text-xs font-medium text-slate-500" :for="`v${index}-opt${n}-name`" x-text="`Option ${n} Name`"></label>
                                                    <input type="text" :id="`v${index}-opt${n}-name`" :name="`variants[${index}][option${n}_name]`" x-model="row[`option${n}_name`]"
                                                        :placeholder="n === 1 ? 'Size' : ''"
                                                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                                </div>
                                                <div class="space-y-1.5">
                                                    <label class="block text-xs font-medium text-slate-500" :for="`v${index}-opt${n}-value`" x-text="`Option ${n} Value`"></label>
                                                    <input type="text" :id="`v${index}-opt${n}-value`" :name="`variants[${index}][option${n}_value]`" x-model="row[`option${n}_value`]"
                                                        :placeholder="n === 1 ? 'Medium' : ''"
                                                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div class="border-t border-slate-100 pt-4">
                                    <p class="vx-eyebrow mb-2.5">Pricing</p>
                                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-medium text-slate-500" :for="`v${index}-price`">Price</label>
                                            <input type="text" inputmode="decimal" :id="`v${index}-price`" :name="`variants[${index}][price]`" x-model="row.price" placeholder="19.99" required
                                                class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-medium text-slate-500" :for="`v${index}-compare`">Compare At</label>
                                            <input type="text" inputmode="decimal" :id="`v${index}-compare`" :name="`variants[${index}][compare_at_price]`" x-model="row.compare_at_price" placeholder="24.99"
                                                class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-medium text-slate-500" :for="`v${index}-cost`">Cost</label>
                                            <input type="text" inputmode="decimal" :id="`v${index}-cost`" :name="`variants[${index}][cost]`" x-model="row.cost" placeholder="8.00"
                                                class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t border-slate-100 pt-4">
                                    <p class="vx-eyebrow mb-2.5">Identifiers &amp; Stock</p>
                                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-medium text-slate-500" :for="`v${index}-sku`">SKU</label>
                                            <input type="text" :id="`v${index}-sku`" :name="`variants[${index}][sku]`" x-model="row.sku"
                                                class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-medium text-slate-500" :for="`v${index}-barcode`">Barcode</label>
                                            <input type="text" :id="`v${index}-barcode`" :name="`variants[${index}][barcode]`" x-model="row.barcode"
                                                class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-medium text-slate-500" :for="`v${index}-weight`">Weight (Grams)</label>
                                            <input type="number" min="0" :id="`v${index}-weight`" :name="`variants[${index}][weight_grams]`" x-model="row.weight_grams"
                                                class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-xs font-medium text-slate-500" :for="`v${index}-qty`">Opening Inventory</label>
                                            <input type="number" :id="`v${index}-qty`" :name="`variants[${index}][inventory_qty]`" x-model="row.inventory_qty"
                                                class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        </div>
                                    </div>
                                    <label class="mt-4 flex cursor-pointer select-none items-center gap-2.5">
                                        <input type="hidden" :name="`variants[${index}][track_inventory]`" :value="row.track_inventory ? 1 : 0">
                                        <button type="button" role="switch" :aria-checked="row.track_inventory.toString()" @click="row.track_inventory = ! row.track_inventory"
                                            :class="row.track_inventory ? 'bg-brand-600' : 'bg-slate-300'"
                                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors">
                                            <span :class="row.track_inventory ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                                        </button>
                                        <span class="text-sm text-slate-700">Track Inventory For This Variant</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </template>

                    <x-button type="button" variant="secondary" icon="plus" @click="addRow()">Add Variant</x-button>
                </div>
            </x-card>
        </div>

        {{-- Organization --}}
        <div x-show="tab === 'organization'" x-cloak class="space-y-6">
            <x-card title="Shipping">
                <x-toggle name="requires_shipping" :checked="old('requires_shipping', $product->requires_shipping)"
                    label="This Product Requires Shipping" description="Switch off for digital goods, services, or gift cards." />
            </x-card>
            <x-card title="Collections" subtitle="Collections are how shoppers browse. A product can sit in several.">
                @if ($collections->isEmpty())
                    <x-empty-state icon="folder" title="No Collections Yet"
                        description="Collections group products into the categories shoppers browse, like 'Kitchen' or 'New Arrivals'. Create one and it will appear here to tick.">
                        <x-slot:action>
                            <x-button href="{{ route('collections.create') }}" size="sm" icon="plus" variant="secondary">New Collection</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($collections as $collection)
                            <div class="rounded-lg px-3 py-2.5 ring-1 ring-slate-200 transition hover:bg-slate-50">
                                <x-check-switch name="collections[]" :value="$collection->id"
                                    :checked="in_array($collection->id, old('collections', $selectedCollections))">
                                    <span class="truncate">{{ $collection->name }}</span>
                                </x-check-switch>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        {{-- SEO --}}
        <div x-show="tab === 'seo'" x-cloak class="space-y-6">
            <x-seo-panel :entity="$product" />
        </div>

        {{-- Save bar. Sticks to the bottom so it is reachable from any tab
             without scrolling back down a long form. Fully opaque: at 95% the
             form text underneath showed through and read as a rendering fault. --}}
        <div x-show="tab !== 'inventory' && tab !== 'images'" x-cloak
            class="sticky bottom-0 z-20 -mx-4 mt-6 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,0.15)] sm:-mx-6 sm:px-6">
            <x-button variant="secondary" href="{{ route('products.index') }}">Cancel</x-button>
            <x-button type="submit" icon="check">{{ $product->exists ? 'Save Changes' : 'Create Product' }}</x-button>
        </div>
    </form>

    {{-- Inventory: a separate quick-adjust form, outside the main product form
         above (browsers cannot nest <form> elements), posting straight to
         products.inventory.update. Only meaningful once variants have ids. --}}
    <div x-show="tab === 'inventory'" x-cloak>
        @if ($product->exists && $product->variants->isNotEmpty())
            <x-card title="Inventory" subtitle="Stock on hand. Saved on its own, independent of the rest of the form.">
                <form method="POST" action="{{ route('products.inventory.update', $product) }}">
                    @csrf
                    @method('PUT')
                    <div class="divide-y divide-slate-100">
                        @foreach ($product->variants as $variant)
                            <div class="flex items-center justify-between gap-4 py-3">
                                <div class="min-w-0">
                                    <label for="stock-{{ $variant->id }}" class="block truncate text-sm font-medium text-slate-900">{{ $variant->name }}</label>
                                    <p class="text-xs text-slate-500">{{ $variant->sku ?: 'No SKU' }} &middot; {{ $variant->track_inventory ? 'Tracked' : 'Not Tracked' }}</p>
                                </div>
                                <input id="stock-{{ $variant->id }}" type="number" name="quantities[{{ $variant->id }}]"
                                    value="{{ old('quantities.'.$variant->id, $variant->inventory_qty) }}"
                                    class="tabular w-28 shrink-0 rounded-lg border-0 bg-white px-3 py-2 text-right text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-1 flex justify-end border-t border-slate-100 pt-4">
                        <x-button type="submit" icon="check">Save Inventory</x-button>
                    </div>
                </form>
            </x-card>
        @else
            <x-card>
                <x-empty-state icon="box" title="Save The Product First"
                    description="Stock is held per variant, so there is nothing to count until at least one variant exists."
                    :steps="[
                        'Open the Variants & Pricing tab.',
                        'Give the variant a price, and a SKU if you use them.',
                        'Save the product, then come back here to set stock.',
                    ]" />
            </x-card>
        @endif
    </div>

    {{-- Images: also a separate form, for the same reason. --}}
    <div x-show="tab === 'images'" x-cloak>
        @if ($product->exists)
            <x-card title="Upload An Image" subtitle="The first image is the one shoppers see in listings." class="mb-6">
                <form method="POST" action="{{ route('products.images.store', $product) }}" enctype="multipart/form-data" x-data="imagePreview()" class="flex flex-wrap items-end gap-4">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700" for="product-image">Image</label>
                        <input id="product-image" type="file" name="image" accept="image/*" required @change="onChange"
                            class="block text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                    </div>
                    <div class="min-w-[12rem] flex-1 space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700" for="alt">Alt Text</label>
                        <x-input id="alt" name="alt" placeholder="Describes the image for screen readers" />
                    </div>
                    <img x-show="preview" :src="preview" x-cloak alt="" class="h-16 w-16 rounded-lg object-cover ring-1 ring-slate-200">
                    <x-button type="submit" icon="plus">Upload</x-button>
                </form>
            </x-card>

            @if ($product->images->isEmpty())
                <x-card>
                    <x-empty-state icon="folder" title="No Images Yet"
                        description="Products with a photo sell better than products without one. Upload at least one using the form above; it becomes the listing thumbnail." />
                </x-card>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($product->images as $image)
                        <div class="group relative overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                            <img src="{{ $image->url }}" alt="{{ $image->alt_text }}" loading="lazy" class="h-36 w-full object-cover">
                            <div class="absolute right-2 top-2">
                                <x-delete-button :action="route('products.images.destroy', [$product, $image])" name="del-image-{{ $image->id }}"
                                    label="Remove Image"
                                    title="Remove This Image?" message="The image is deleted from the product gallery and from storage. This cannot be undone." />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <x-card>
                <x-empty-state icon="folder" title="Save The Product First"
                    description="Images are attached to a saved product, so create it on the General tab and then upload from here." />
            </x-card>
        @endif
    </div>
</div>
