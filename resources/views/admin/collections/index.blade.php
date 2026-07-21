<x-layouts.app title="Collections">
    <x-page-header
        eyebrow="Catalog"
        title="Collections"
        icon="folder"
        subtitle="The groupings shoppers browse by on the storefront.">
        <x-slot:primary>
            <x-button href="{{ route('collections.create') }}" icon="plus">New Collection</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($collections->isEmpty() && empty($filters['q']))
        <x-card>
            <x-empty-state icon="folder" title="No Collections Yet"
                description="A collection is how shoppers find things: 'Kitchen', 'Under $50', 'New This Season'. A product can belong to as many as you like, and the storefront builds its navigation from the active ones."
                :steps="[
                    'Name the collection the way a shopper would think of it, not the way your stockroom does.',
                    'Pick the products that belong in it.',
                    'Set it Active so it appears in storefront navigation.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('collections.create') }}">Create Your First Collection</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                allIds: [{{ $collections->pluck('id')->implode(',') }}],
                submitBulk() {
                    const form = this.$refs.bulkForm;
                    form.querySelectorAll('input.js-dyn').forEach(node => node.remove());
                    this.selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden'; input.name = 'ids[]'; input.value = id; input.className = 'js-dyn';
                        form.appendChild(input);
                    });
                    form.submit();
                }
            }">
            <form method="POST" action="{{ route('collections.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:search>
                    <form method="GET" action="{{ route('collections.index') }}" class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="collection-search" class="sr-only">Search Collections</label>
                            <input id="collection-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Collection Name"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-64">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('collections.index') }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-collections')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($collections->isEmpty())
                    <x-empty-state icon="search" title="No Collections Match That Search"
                        description="Try a shorter search term, or clear it to see everything.">
                        <x-slot:action>
                            <x-button href="{{ route('collections.index') }}" variant="secondary" size="sm">Show All Collections</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Collection</th>
                                <th class="text-right">Products</th>
                                <th>Status</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($collections as $collection)
                                {{-- An empty collection renders as an empty page on the
                                     storefront, so it is flagged as something to fix. --}}
                                <tr class="vx-rail {{ $collection->is_active && $collection->products_count === 0 ? 'vx-rail-warn' : 'vx-rail-none' }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $collection->id])</td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-300 ring-1 ring-slate-200">
                                                @if ($collection->image_url)
                                                    <img src="{{ $collection->image_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                                @else
                                                    <x-icon name="folder" class="h-4 w-4" aria-hidden="true" />
                                                @endif
                                            </span>
                                            <div class="min-w-0">
                                                <a href="{{ route('collections.edit', $collection) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $collection->name }}</a>
                                                @if ($collection->is_active && $collection->products_count === 0)
                                                    <span class="block text-xs text-amber-700">Active but empty</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="tabular text-right {{ $collection->products_count === 0 ? 'text-slate-400' : 'text-slate-700' }}">{{ $collection->products_count }}</td>
                                    <td><x-badge :color="$collection->is_active ? 'success' : 'neutral'" dot>{{ $collection->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-button href="{{ route('collections.edit', $collection) }}" icon="edit" title="Edit Collection" />
                                            <x-delete-button :action="route('collections.destroy', $collection)" name="del-collection-{{ $collection->id }}"
                                                label="Delete Collection"
                                                title="Delete This Collection?"
                                                :message="'This removes the grouping \'' . $collection->name . '\'. Every product in it stays in your catalog and keeps its other collections.'" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-collections" title="Delete Selected Collections?" icon="warning" tone="danger" maxWidth="max-w-md">
                This removes the selected groupings. Every product inside them stays in your catalog and keeps its other collections. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-collections')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-collections')">Delete Collections</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $collections->links() }}</div>
    @endif
</x-layouts.app>
