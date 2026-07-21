<x-layouts.app title="Templates">
    <x-page-header
        eyebrow="Appearance"
        title="Templates"
        icon="edit"
        subtitle="Edit the real Blade behind any page of your shop. Every save is checked before it goes live.">
        <x-slot:actions>
            <x-button variant="secondary" icon="star" href="{{ route('themes.index') }}">Themes</x-button>
        </x-slot:actions>
        <x-slot:primary>
            <x-button variant="secondary" icon="external" href="{{ route('shop.home') }}" target="_blank" rel="noopener">View Store</x-button>
        </x-slot:primary>
    </x-page-header>

    @if (count($previewing))
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-inset ring-amber-200">
            <div class="flex min-w-0 items-start gap-3">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-amber-600 ring-1 ring-amber-200">
                    <x-icon name="eye" class="h-5 w-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-amber-900">Unpublished Preview Active</p>
                    <p class="mt-0.5 text-sm text-amber-800">
                        You are seeing draft versions of {{ implode(', ', $previewing) }}. Nobody else is. It expires on its own.
                    </p>
                </div>
            </div>
            <form method="POST" action="{{ route('templates.preview.stop') }}">
                @csrf
                <x-button type="submit" variant="secondary" size="sm" icon="x-circle">Stop Preview</x-button>
            </form>
        </div>
    @endif

    <x-card class="mb-6">
        <div class="flex items-start gap-3.5">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-200">
                <x-icon name="shield" class="h-5 w-5" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <h3 class="text-[15px] font-semibold text-slate-900">How Editing Works Here</h3>
                <p class="mt-1 max-w-prose text-sm leading-relaxed text-slate-500">
                    Your edits are stored in this store's database, never written back over the files a ShopMGR
                    release ships. That means an update cannot overwrite your work, and your work cannot block an
                    update. Every save is compiled and parse-checked first, so a template with a syntax error is
                    rejected instead of taking a page down. Any template can be reverted to an earlier version, or
                    reset to the shipped default, at any time.
                </p>
            </div>
        </div>
    </x-card>

    <div x-data="{ tab: '{{ array_key_first($groups) }}' }">
        <x-segmented label="Template Groups" class="mb-4">
            @foreach ($groups as $group)
                <button type="button" class="vx-seg-item" :class="tab === '{{ $group['key'] }}' && 'is-active'"
                        x-on:click="tab = '{{ $group['key'] }}'" role="tab" :aria-selected="(tab === '{{ $group['key'] }}').toString()">
                    <x-icon :name="$group['icon']" class="h-4 w-4 shrink-0" aria-hidden="true" />
                    {{ $group['label'] }}
                    <span class="vx-seg-count">{{ count($group['rows']) }}</span>
                </button>
            @endforeach
        </x-segmented>

        @foreach ($groups as $group)
            <div x-show="tab === '{{ $group['key'] }}'" x-cloak>
                <x-data-surface>
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Template</th>
                                <th>Risk</th>
                                <th>State</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['rows'] as $row)
                                <tr class="vx-rail {{ $row['overridden'] ? 'vx-rail-info' : 'vx-rail-none' }}">
                                    <td>
                                        <a href="{{ route('templates.edit', $row['view']) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $row['label'] }}</a>
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $row['description'] }}</span>
                                        <span class="mt-1 block font-mono text-[11px] text-slate-400">{{ $row['view'] }}</span>
                                    </td>
                                    <td>
                                        @if ($row['risk'] === 'high')
                                            <x-badge color="warn" dot>High Risk</x-badge>
                                        @else
                                            <x-badge color="neutral">Standard</x-badge>
                                        @endif
                                    </td>
                                    <td>
                                        @if (! $row['exists'])
                                            <x-badge color="danger" dot>File Missing</x-badge>
                                        @elseif ($row['overridden'])
                                            <x-badge color="info" dot>Customised</x-badge>
                                        @else
                                            <span class="text-slate-400">Shipped Default</span>
                                        @endif
                                    </td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-button href="{{ route('templates.edit', $row['view']) }}" icon="edit" title="Edit Template" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                </x-data-surface>

                @if ($group['description'])
                    <p class="mt-3 text-sm text-slate-500">{{ $group['description'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.app>
