<x-layouts.app title="Products">
    <x-page-header
        eyebrow="Catalog"
        title="Products"
        icon="bag"
        subtitle="Everything you sell, with its variants, stock, and pricing.">
        <x-slot:primary>
            <x-button href="{{ route('products.create') }}" icon="plus">New Product</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($products->isEmpty() && ! $hasFilters)
        <x-card>
            <x-empty-state icon="bag" title="Your Catalog Is Empty"
                description="A product holds the description and images shoppers see. Its variants hold the things that differ per option: price, SKU, and stock. Even a product sold one way has one variant behind it."
                :steps="[
                    'Create the product and give it a name and description.',
                    'Add a variant with its price and opening stock, then save.',
                    'Upload an image and set the status to Active to put it on the storefront.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('products.create') }}">Create Your First Product</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                confirming: false,
                allIds: [{{ $products->pluck('id')->implode(',') }}],
                post(ref, extra = {}) {
                    const form = this.$refs[ref];
                    form.querySelectorAll('input.js-dyn').forEach(node => node.remove());
                    this.selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden'; input.name = 'ids[]'; input.value = id; input.className = 'js-dyn';
                        form.appendChild(input);
                    });
                    Object.entries(extra).forEach(([name, value]) => {
                        const field = form.querySelector(`input[name=${name}]`);
                        if (field) field.value = value;
                    });
                    form.submit();
                }
            }">
            <form method="POST" action="{{ route('products.bulk-destroy') }}" x-ref="bulkDeleteForm" class="hidden">@csrf @method('DELETE')</form>
            <form method="POST" action="{{ route('products.bulk-status') }}" x-ref="bulkStatusForm" class="hidden">@csrf<input type="hidden" name="status" value=""></form>

            <x-data-surface>
                <x-slot:toolbar>
                    <x-segmented label="Product Status">
                        @foreach ($tabs as $tab)
                            <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                                @if ($tab['active']) aria-current="page" @endif>
                                {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </x-segmented>
                </x-slot:toolbar>

                <x-slot:search>
                    <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap items-center gap-2">
                        @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="product-search" class="sr-only">Search Products</label>
                            <input id="product-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Name, Vendor, Or SKU"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-56">
                        </div>
                        <label for="product-collection" class="sr-only">Filter By Collection</label>
                        <select id="product-collection" name="collection" onchange="this.form.submit()"
                            class="rounded-lg border-0 bg-white py-1.5 pl-3 pr-8 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            <option value="">All Collections</option>
                            @foreach ($collections as $collection)
                                <option value="{{ $collection->id }}" @selected(($filters['collection'] ?? null) == $collection->id)>{{ $collection->name }}</option>
                            @endforeach
                        </select>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']) || ! empty($filters['collection']))
                            <x-button variant="ghost" size="sm" href="{{ route('products.index', array_filter(['status' => $filters['status'] ?? ''])) }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="secondary" size="sm" icon="check-circle"
                                x-on:click="post('bulkStatusForm', { status: 'active' })">Publish</x-button>
                            <x-button type="button" variant="secondary" size="sm" icon="edit"
                                x-on:click="post('bulkStatusForm', { status: 'draft' })">Unpublish</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-products')">Delete</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($products->isEmpty())
                    <x-empty-state icon="search" title="No Products Match These Filters"
                        description="Nothing here fits the current tab, search, and collection together. Try clearing one of them.">
                        <x-slot:action>
                            <x-button href="{{ route('products.index') }}" variant="secondary" size="sm">Show All Products</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th class="text-right">Inventory</th>
                                <th class="text-right">Price</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr class="vx-rail vx-rail-{{ $product->status === 'active' ? $product->inventory_badge : $product->status_badge }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $product->id])</td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-300 ring-1 ring-slate-200">
                                                @if ($product->images->first())
                                                    <img src="{{ $product->images->first()->url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                                @else
                                                    <x-icon name="bag" class="h-4 w-4" aria-hidden="true" />
                                                @endif
                                            </span>
                                            <div class="min-w-0">
                                                <a href="{{ route('products.edit', $product) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $product->name }}</a>
                                                <span class="block truncate text-xs text-slate-500">{{ $product->vendor ?: 'No Vendor' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><x-badge :color="$product->status_badge" dot>{{ \Illuminate\Support\Str::headline($product->status) }}</x-badge></td>
                                    <td class="tabular text-right {{ $product->inventory_badge === 'danger' ? 'font-semibold text-rose-600' : ($product->inventory_badge === 'warn' ? 'font-medium text-amber-700' : 'text-slate-700') }}">
                                        {{ $product->total_inventory }}
                                    </td>
                                    <td class="tabular text-right text-slate-900">
                                        {{ $product->price_from_formatted }}@if ($product->has_price_range)<span class="text-slate-400"> &ndash; {{ $product->price_to_formatted }}</span>@endif
                                    </td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-button href="{{ route('products.edit', $product) }}" icon="edit" title="Edit Product" />
                                            <x-icon-button icon="copy" title="Duplicate Product" x-data @click="$dispatch('open-modal', 'dup-{{ $product->id }}')" />
                                            <x-delete-button :action="route('products.destroy', $product)" name="del-product-{{ $product->id }}"
                                                label="Delete Product"
                                                title="Delete This Product?"
                                                :message="'This removes \'' . $product->name . '\' and all of its variants. Past orders keep their line-item history, so your reporting stays intact.'" />
                                        </div>
                                        <x-modal name="dup-{{ $product->id }}" title="Duplicate This Product?" icon="copy" maxWidth="max-w-md">
                                            A draft copy of "{{ $product->name }}" will be created with the same variants and pricing. It stays unpublished until you set it to Active.
                                            <x-slot:footer>
                                                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'dup-{{ $product->id }}')">Cancel</x-button>
                                                <form method="POST" action="{{ route('products.duplicate', $product) }}">
                                                    @csrf
                                                    <x-button variant="primary" size="sm" type="submit" icon="copy">Duplicate</x-button>
                                                </form>
                                            </x-slot:footer>
                                        </x-modal>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-products" title="Delete Selected Products?" icon="warning" tone="danger" maxWidth="max-w-md">
                This permanently removes the selected products and their variants. Past orders keep their line-item history, so reporting is unaffected. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-products')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="post('bulkDeleteForm'); $dispatch('close-modal', 'bulk-delete-products')">Delete Products</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-layouts.app>
