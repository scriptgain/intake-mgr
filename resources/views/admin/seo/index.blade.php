{{-- SEO health. An exception list in the Ledger reading used across the admin:
     nothing that is fine is rendered, every row carries the colour of its
     severity on a rail, and the strip at the top is a worklist of tasks rather
     than a score. Tabs, not a long scroll. --}}
<x-layouts.app title="SEO Health">
    <x-page-header
        eyebrow="Catalog"
        title="SEO Health"
        icon="globe"
        subtitle="Products whose search listing is missing, duplicated, or the wrong length.">
        <x-slot:actions>
            <x-button href="{{ $sitemapUrl }}" target="_blank" rel="noopener" variant="secondary" size="sm" icon="external">Sitemap</x-button>
        </x-slot:actions>
        <x-slot:primary>
            <x-button href="{{ route('settings.seo.edit') }}" size="sm" icon="settings">SEO Settings</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($siteNoindex)
        <x-alert type="warn" title="Search Engines Are Being Discouraged" class="mb-6">
            Nothing below can rank while the site-wide noindex switch is on. Turn it off at Settings then SEO before launch.
        </x-alert>
    @endif

    {{-- Coverage. One surface, one dominant number, supporting figures
         subordinate underneath. --}}
    <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200" aria-labelledby="coverage-heading">
        <div class="p-5 sm:p-6">
            <h2 id="coverage-heading" class="vx-eyebrow">Fully Optimised Products</h2>
            <div class="mt-2.5 flex flex-wrap items-baseline gap-3">
                <span class="tabular text-4xl font-semibold tracking-tight text-slate-900">{{ $cleanCount }}</span>
                <span class="text-sm text-slate-500">Of {{ $totalProducts }} Live And Draft Products</span>
            </div>
            <p class="mt-1.5 text-sm text-slate-500">A product counts as optimised when it has a written title and description of a sensible length, at least one image, and is not excluded from search.</p>
        </div>
    </section>

    <div class="section-divider my-8"></div>

    {{-- Needs Attention. Same worklist shape as the dashboard. --}}
    <section aria-labelledby="attention-heading">
        <h2 id="attention-heading" class="mb-3 text-sm font-semibold text-slate-900">Needs Attention</h2>
        @if (empty($worklist))
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                <x-empty-state icon="check-circle" title="Every Product Is Optimised"
                    description="Nothing is missing a title or description, no two products compete on the same wording, every product has an image, and nothing is excluded from search." />
            </div>
        @else
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($worklist as $item)
                    <li>
                        <a href="#seo-{{ $item['key'] }}"
                            class="vx-rail vx-rail-{{ $item['tone'] }} group flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md hover:ring-brand-300">
                            <span @class([
                                'inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg ring-1',
                                'bg-amber-50 text-amber-600 ring-amber-200' => $item['tone'] === 'warn',
                                'bg-rose-50 text-rose-600 ring-rose-200' => $item['tone'] === 'danger',
                                'bg-brand-50 text-brand-600 ring-brand-200' => $item['tone'] === 'info',
                            ])>
                                <x-icon :name="$item['icon']" class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="tabular text-2xl font-semibold leading-none text-slate-900">{{ $item['count'] }}</p>
                                <p class="mt-1.5 truncate text-sm text-slate-500">{{ $item['label'] }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-brand-700 opacity-0 transition group-hover:opacity-100">
                                {{ $item['action'] }}
                                <x-icon name="chevron-right" class="h-4 w-4" aria-hidden="true" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if (! empty($groups))
        <div class="section-divider my-8"></div>

        <section x-data="{ tab: '{{ array_key_first($groups) }}' }" aria-labelledby="issues-heading">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 id="issues-heading" class="text-sm font-semibold text-slate-900">Issues</h2>
                <x-segmented label="SEO Issue Types">
                    @foreach ($tabs as $tab)
                        <button type="button" role="tab" id="seo-{{ $tab['key'] }}"
                            :aria-selected="(tab === '{{ $tab['key'] }}').toString()" @click="tab = '{{ $tab['key'] }}'"
                            class="vx-seg-item" :class="tab === '{{ $tab['key'] }}' && 'is-active'">
                            {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                        </button>
                    @endforeach
                </x-segmented>
            </div>

            @foreach ($groups as $key => $group)
                <div x-show="tab === '{{ $key }}'" x-cloak>
                    <x-data-surface>
                        <div class="border-b border-slate-100 px-5 py-3.5 sm:px-6">
                            <p class="text-sm text-slate-500">{{ $group['blurb'] }}</p>
                        </div>
                        <x-table flush>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th class="vx-wrap">Resolved Title</th>
                                    <th class="text-right">Title</th>
                                    <th class="text-right">Description</th>
                                    <th class="vx-col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($group['rows'] as $row)
                                    <tr class="vx-rail vx-rail-{{ $group['tone'] }}">
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-300 ring-1 ring-slate-200">
                                                    @if ($row['image_url'])
                                                        <img src="{{ $row['image_url'] }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                                    @else
                                                        <x-icon name="bag" class="h-4 w-4" aria-hidden="true" />
                                                    @endif
                                                </span>
                                                <a href="{{ $row['edit_url'] }}" class="block max-w-[16rem] truncate font-medium text-slate-900 hover:text-brand-700">{{ $row['name'] }}</a>
                                            </div>
                                        </td>
                                        <td><x-badge :color="$row['status_badge']" dot>{{ \Illuminate\Support\Str::headline($row['status']) }}</x-badge></td>
                                        <td class="vx-wrap max-w-[22rem] text-slate-500">{{ $row['resolved_title'] }}</td>
                                        <td class="text-right">
                                            @if ($row['title_issue'])
                                                <x-badge color="warn">{{ $row['title_issue'] }}</x-badge>
                                            @else
                                                <span class="tabular text-slate-500">{{ $row['title_length'] }}</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($row['description_issue'])
                                                <x-badge color="warn">{{ $row['description_issue'] }}</x-badge>
                                            @else
                                                <span class="tabular text-slate-500">{{ $row['description_length'] }}</span>
                                            @endif
                                        </td>
                                        <td class="vx-col-actions">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <a href="{{ $row['view_url'] }}" target="_blank" rel="noopener"
                                                    data-tip="Open On The Storefront"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-50 hover:text-slate-900">
                                                    <x-icon name="external" class="h-4 w-4" aria-hidden="true" />
                                                </a>
                                                <x-button href="{{ $row['edit_url'] }}" variant="secondary" size="sm" icon="edit">Edit</x-button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table>
                    </x-data-surface>
                </div>
            @endforeach
        </section>
    @endif

    @if (! empty($collectionIssues))
        <div class="section-divider my-8"></div>

        <section aria-labelledby="collections-heading">
            <h2 id="collections-heading" class="mb-3 text-sm font-semibold text-slate-900">Collections</h2>
            <x-data-surface>
                <x-table flush>
                    <thead>
                        <tr>
                            <th>Collection</th>
                            <th>Issue</th>
                            <th class="text-right">Title</th>
                            <th class="text-right">Description</th>
                            <th class="vx-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($collectionIssues as $row)
                            <tr class="vx-rail vx-rail-warn">
                                <td><a href="{{ $row['edit_url'] }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $row['name'] }}</a></td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($row['missing_title'])<x-badge color="warn">No Meta Title</x-badge>@endif
                                        @if ($row['missing_description'])<x-badge color="warn">No Meta Description</x-badge>@endif
                                        @if ($row['noindex'])<x-badge color="info">Excluded From Search</x-badge>@endif
                                    </div>
                                </td>
                                <td class="tabular text-right text-slate-500">{{ $row['title_length'] }}</td>
                                <td class="tabular text-right text-slate-500">{{ $row['description_length'] }}</td>
                                <td class="vx-col-actions">
                                    <x-button href="{{ $row['edit_url'] }}" variant="secondary" size="sm" icon="edit">Edit</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            </x-data-surface>
        </section>
    @endif
</x-layouts.app>
