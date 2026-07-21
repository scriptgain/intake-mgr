<x-layouts.app title="Policy Pages">
    <x-page-header
        eyebrow="Help Center"
        title="Policy Pages"
        icon="info"
        subtitle="Your store's standalone pages: shipping, refunds, terms, and privacy.">
        <x-slot:primary>
            <x-button href="{{ route('store-pages.create') }}" icon="plus">New Page</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($pages->isEmpty() && empty($filters['q']))
        <x-card>
            <x-empty-state icon="info" title="No Pages Yet"
                description="Policy pages are the flat, standalone pages your footer links to, like Shipping Information or Refund Policy. Bodies are written in Markdown.">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('store-pages.create') }}">Create Your First Page</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                allIds: [{{ $pages->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('store-pages.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:search>
                    <form method="GET" action="{{ route('store-pages.index') }}" class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="page-search" class="sr-only">Search Pages</label>
                            <input id="page-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Page Title"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-64">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('store-pages.index') }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-store-pages')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($pages->isEmpty())
                    <x-empty-state icon="search" title="No Pages Match That Search"
                        description="Try a shorter search term, or clear it to see everything.">
                        <x-slot:action>
                            <x-button href="{{ route('store-pages.index') }}" variant="secondary" size="sm">Show All Pages</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Page</th>
                                <th>Slug</th>
                                <th>Updated</th>
                                <th>Status</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pages as $page)
                                <tr class="vx-rail {{ $page->is_published ? 'vx-rail-none' : 'vx-rail-warn' }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $page->id])</td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200">
                                                <x-icon name="info" class="h-4 w-4" aria-hidden="true" />
                                            </span>
                                            <a href="{{ route('store-pages.edit', $page) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $page->title }}</a>
                                        </div>
                                    </td>
                                    <td><span class="tabular text-sm text-slate-500">/pages/{{ $page->slug }}</span></td>
                                    <td class="text-sm text-slate-500">{{ $page->updated_at?->format(config('shop.date_format', 'M j, Y')) }}</td>
                                    <td><x-badge :color="$page->is_published ? 'success' : 'neutral'" dot>{{ $page->is_published ? 'Published' : 'Draft' }}</x-badge></td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-button href="{{ route('store-pages.edit', $page) }}" icon="edit" title="Edit Page" />
                                            <x-delete-button :action="route('store-pages.destroy', $page)" name="del-store-page-{{ $page->id }}"
                                                label="Delete Page"
                                                title="Delete This Page?"
                                                :message="'This permanently removes the \'' . $page->title . '\' page. Any footer link to it will 404. This cannot be undone.'" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-store-pages" title="Delete Selected Pages?" icon="warning" tone="danger" maxWidth="max-w-md">
                This permanently removes the selected pages. Any footer links to them will 404. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-store-pages')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-store-pages')">Delete Pages</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $pages->links() }}</div>
    @endif
</x-layouts.app>
