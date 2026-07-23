<x-layouts.app title="Booking Types">
    <x-page-header
        eyebrow="Scheduling"
        title="Booking Types"
        icon="clock"
        subtitle="The kinds of appointment you offer, each with its own duration, buffers, and price.">
        <x-slot:primary>
            <x-button href="{{ route('booking-types.create') }}" icon="plus">New Booking Type</x-button>
        </x-slot:primary>
    </x-page-header>

    @unless ($bookingTypes->isEmpty() && ! array_filter($filters))
        <x-alert type="info" title="Each Active Type Has A Public Booking Link" class="mb-6">
            Customers can self-schedule an open slot at
            <code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">/book/&lt;name&gt;</code>.
            Use the copy icon on any active row to grab its link, or the arrow to preview the page. Slots reflect the
            technician's availability minus their connected-calendar busy times.
        </x-alert>
    @endunless

    @if ($bookingTypes->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="clock" title="No Booking Types Yet"
                description="A booking type is a named kind of appointment, like a Standard Service Call or an Emergency Callout. It sets how long the appointment runs, how much padding to leave around it, and who normally handles it."
                :steps="[
                    'Create a booking type and give it a name and duration.',
                    'Add any buffer time and an optional price or default technician.',
                    'Keep it active so it can be scheduled.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('booking-types.create') }}">Create Your First Booking Type</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                confirming: false,
                allIds: [{{ $bookingTypes->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('booking-types.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:toolbar>
                    <x-segmented label="Booking Type Status">
                        @foreach ($tabs as $tab)
                            <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                                @if ($tab['active']) aria-current="page" @endif>
                                {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </x-segmented>
                </x-slot:toolbar>

                <x-slot:search>
                    <form method="GET" action="{{ route('booking-types.index') }}" class="flex flex-wrap items-center gap-2">
                        @if (isset($filters['active']) && $filters['active'] !== null && $filters['active'] !== '')<input type="hidden" name="active" value="{{ $filters['active'] }}">@endif
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="booking-type-search" class="sr-only">Search Booking Types</label>
                            <input id="booking-type-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Name Or Description"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-64">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('booking-types.index', array_filter(['active' => $filters['active'] ?? ''], fn ($v) => $v !== null && $v !== '')) }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-booking-types')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($bookingTypes->isEmpty())
                    <x-empty-state icon="search" title="No Booking Types Match These Filters"
                        description="Nothing here fits the current tab and search. Widen the search or switch back to All.">
                        <x-slot:action>
                            <x-button href="{{ route('booking-types.index') }}" variant="secondary" size="sm">Show All Booking Types</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Name</th>
                                <th>Duration</th>
                                <th class="text-right">Price</th>
                                <th>Technician</th>
                                <th>Status</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookingTypes as $bookingType)
                                <tr class="vx-rail vx-rail-{{ $bookingType->is_active ? 'success' : 'neutral' }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $bookingType->id])</td>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <span class="h-2.5 w-2.5 shrink-0 rounded-full ring-1 ring-inset ring-slate-200" style="background-color: {{ $bookingType->color ?: '#cbd5e1' }}"></span>
                                            <div class="min-w-0">
                                                <a href="{{ route('booking-types.edit', $bookingType) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $bookingType->name }}</a>
                                                @if ($bookingType->description)
                                                    <span class="block max-w-xs truncate text-xs text-slate-500">{{ $bookingType->description }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-slate-600">
                                        {{ $bookingType->duration_minutes }} Min
                                        @if ($bookingType->total_minutes !== (int) $bookingType->duration_minutes)
                                            <span class="text-xs text-slate-400">({{ $bookingType->total_minutes }} With Buffers)</span>
                                        @endif
                                    </td>
                                    <td class="tabular text-right text-slate-900">{{ $bookingType->price_formatted }}</td>
                                    <td class="text-slate-600">{{ $bookingType->assignee?->name ?? 'Any Available' }}</td>
                                    <td>
                                        <x-badge :color="$bookingType->is_active ? 'success' : 'neutral'" dot>{{ $bookingType->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                    </td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($bookingType->is_active)
                                                {{-- Copy public booking link. Styled tooltip teleported to <body>
                                                     (fleet rule: never a clippable CSS tooltip), with a copied state. --}}
                                                <span class="inline-flex"
                                                      x-data="{ tip: false, tx: 0, ty: 0, copied: false }"
                                                      @mouseenter="const r = $el.getBoundingClientRect(); tx = r.left + r.width / 2; ty = r.top - 8; tip = true"
                                                      @mouseleave="tip = false">
                                                    <button type="button" aria-label="Copy Public Booking Link"
                                                            x-on:click="navigator.clipboard.writeText(@js(route('shop.book', $bookingType))); copied = true; setTimeout(() => copied = false, 1500)"
                                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white text-slate-600 ring-1 ring-inset ring-slate-300 transition hover:bg-slate-50 hover:text-slate-900">
                                                        <x-icon x-show="!copied" name="copy" class="h-4 w-4" />
                                                        <x-icon x-show="copied" x-cloak name="check" class="h-4 w-4 text-emerald-600" />
                                                    </button>
                                                    <template x-teleport="body">
                                                        <div x-show="tip" x-cloak :style="`left:${tx}px;top:${ty}px`"
                                                             class="fixed -translate-x-1/2 -translate-y-full pointer-events-none z-[100] whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-lg"
                                                             x-text="copied ? 'Copied!' : 'Copy Public Booking Link'"></div>
                                                    </template>
                                                </span>
                                                <x-icon-button href="{{ route('shop.book', $bookingType) }}" icon="external"
                                                    title="Open Public Booking Page" target="_blank" rel="noopener" />
                                            @endif
                                            <x-icon-button href="{{ route('booking-types.edit', $bookingType) }}" icon="edit" title="Edit Booking Type" />
                                            <x-delete-button :action="route('booking-types.destroy', $bookingType)" name="del-booking-type-{{ $bookingType->id }}"
                                                label="Delete Booking Type"
                                                title="Delete This Booking Type?"
                                                :message="'This removes \'' . $bookingType->name . '\'. Existing appointments are not affected. This cannot be undone.'" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-booking-types" title="Delete Selected Booking Types?" icon="warning" tone="danger" maxWidth="max-w-md">
                This permanently removes the selected booking types. Existing appointments are not affected. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-booking-types')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-booking-types')">Delete Booking Types</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $bookingTypes->links() }}</div>
    @endif
</x-layouts.app>
