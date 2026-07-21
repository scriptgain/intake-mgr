<x-layouts.app title="Help Categories">
    <x-page-header
        eyebrow="Help Center"
        title="Categories"
        icon="folder"
        subtitle="The topics shoppers browse in your storefront Help Center.">
        <x-slot:primary>
            <x-button href="{{ route('help-categories.create') }}" icon="plus">New Category</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($categories->isEmpty() && empty($filters['q']))
        <x-card>
            <x-empty-state icon="folder" title="No Help Categories Yet"
                description="Categories group your help articles into topics like Shipping, Returns, or Payments. The storefront builds its Help Center from the published ones."
                :steps="[
                    'Name the topic the way a shopper would think of it.',
                    'Give it an icon and a short description.',
                    'Publish it, then add articles inside it.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('help-categories.create') }}">Create Your First Category</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                allIds: [{{ $categories->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('help-categories.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:search>
                    <form method="GET" action="{{ route('help-categories.index') }}" class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="category-search" class="sr-only">Search Categories</label>
                            <input id="category-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Category Name"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-64">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('help-categories.index') }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-help-categories')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($categories->isEmpty())
                    <x-empty-state icon="search" title="No Categories Match That Search"
                        description="Try a shorter search term, or clear it to see everything.">
                        <x-slot:action>
                            <x-button href="{{ route('help-categories.index') }}" variant="secondary" size="sm">Show All Categories</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Category</th>
                                <th class="text-right">Articles</th>
                                <th>Position</th>
                                <th>Status</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr class="vx-rail {{ $category->is_published && $category->articles_count === 0 ? 'vx-rail-warn' : 'vx-rail-none' }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $category->id])</td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                                                <x-icon :name="$category->icon ?: 'book'" class="h-4 w-4" aria-hidden="true" />
                                            </span>
                                            <div class="min-w-0">
                                                <a href="{{ route('help-categories.edit', $category) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $category->name }}</a>
                                                @if ($category->is_published && $category->articles_count === 0)
                                                    <span class="block text-xs text-amber-700">Published but empty</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="tabular text-right {{ $category->articles_count === 0 ? 'text-slate-400' : 'text-slate-700' }}">{{ $category->articles_count }}</td>
                                    <td class="tabular text-slate-500">{{ $category->position }}</td>
                                    <td><x-badge :color="$category->is_published ? 'success' : 'neutral'" dot>{{ $category->is_published ? 'Published' : 'Draft' }}</x-badge></td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-button href="{{ route('help-categories.edit', $category) }}" icon="edit" title="Edit Category" />
                                            <x-delete-button :action="route('help-categories.destroy', $category)" name="del-help-category-{{ $category->id }}"
                                                label="Delete Category"
                                                title="Delete This Category?"
                                                :message="'This removes the topic \'' . $category->name . '\' and every article inside it. This cannot be undone.'" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-help-categories" title="Delete Selected Categories?" icon="warning" tone="danger" maxWidth="max-w-md">
                This removes the selected topics and every article inside them. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-help-categories')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-help-categories')">Delete Categories</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $categories->links() }}</div>
    @endif
</x-layouts.app>
