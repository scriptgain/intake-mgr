<x-layouts.app title="Help Articles">
    <x-page-header
        eyebrow="Help Center"
        title="Articles"
        icon="book"
        subtitle="The individual answers shoppers read in your Help Center.">
        <x-slot:primary>
            <x-button href="{{ route('help-articles.create') }}" icon="plus">New Article</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($articles->isEmpty() && empty($filters['q']) && empty($filters['category']))
        <x-card>
            @if ($categories->isEmpty())
                <x-empty-state icon="book" title="Create A Category First"
                    description="Articles live inside categories. Add at least one category, then come back to write articles.">
                    <x-slot:action>
                        <x-button icon="plus" href="{{ route('help-categories.create') }}">New Category</x-button>
                    </x-slot:action>
                </x-empty-state>
            @else
                <x-empty-state icon="book" title="No Articles Yet"
                    description="An article is a single question and its answer. Bodies are written in Markdown and render cleanly on the storefront."
                    :steps="[
                        'Pick the category the article belongs in.',
                        'Write a clear title a shopper would search for.',
                        'Answer it in the body, then publish.',
                    ]">
                    <x-slot:action>
                        <x-button icon="plus" href="{{ route('help-articles.create') }}">Write Your First Article</x-button>
                    </x-slot:action>
                </x-empty-state>
            @endif
        </x-card>
    @else
        <div x-data="{
                selected: [],
                allIds: [{{ $articles->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('help-articles.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:search>
                    <form method="GET" action="{{ route('help-articles.index') }}" class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="article-search" class="sr-only">Search Articles</label>
                            <input id="article-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Article Title"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-56">
                        </div>
                        <label for="article-category" class="sr-only">Filter By Category</label>
                        <x-select id="article-category" name="category" class="sm:w-48" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((string) ($filters['category'] ?? '') === (string) $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </x-select>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']) || ! empty($filters['category']))
                            <x-button variant="ghost" size="sm" href="{{ route('help-articles.index') }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-help-articles')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($articles->isEmpty())
                    <x-empty-state icon="search" title="No Articles Match Those Filters"
                        description="Try a shorter search term or a different category.">
                        <x-slot:action>
                            <x-button href="{{ route('help-articles.index') }}" variant="secondary" size="sm">Show All Articles</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Article</th>
                                <th>Category</th>
                                <th class="text-right">Views</th>
                                <th>Status</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($articles as $article)
                                <tr class="vx-rail {{ $article->is_published ? 'vx-rail-none' : 'vx-rail-warn' }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $article->id])</td>
                                    <td>
                                        <div class="min-w-0">
                                            <a href="{{ route('help-articles.edit', $article) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $article->title }}</a>
                                            @if ($article->excerpt)
                                                <span class="block truncate text-xs text-slate-500">{{ $article->excerpt }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if ($article->category)
                                            <span class="inline-flex items-center gap-1.5 text-sm text-slate-600"><x-icon :name="$article->category->icon ?: 'book'" class="h-4 w-4 text-slate-400" /> {{ $article->category->name }}</span>
                                        @else
                                            <span class="text-sm text-slate-400">None</span>
                                        @endif
                                    </td>
                                    <td class="tabular text-right text-slate-500">{{ number_format($article->views) }}</td>
                                    <td><x-badge :color="$article->is_published ? 'success' : 'neutral'" dot>{{ $article->is_published ? 'Published' : 'Draft' }}</x-badge></td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-button href="{{ route('help-articles.edit', $article) }}" icon="edit" title="Edit Article" />
                                            <x-delete-button :action="route('help-articles.destroy', $article)" name="del-help-article-{{ $article->id }}"
                                                label="Delete Article"
                                                title="Delete This Article?"
                                                :message="'This permanently removes \'' . $article->title . '\'. This cannot be undone.'" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-help-articles" title="Delete Selected Articles?" icon="warning" tone="danger" maxWidth="max-w-md">
                This permanently removes the selected articles. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-help-articles')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-help-articles')">Delete Articles</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $articles->links() }}</div>
    @endif
</x-layouts.app>
