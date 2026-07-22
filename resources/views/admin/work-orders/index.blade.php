<x-layouts.app title="Work Orders">
    <x-page-header
        eyebrow="Service Desk"
        title="Work Orders"
        icon="truck"
        subtitle="Scheduled service work, its line items, and where each job stands.">
        <x-slot:primary>
            <x-button href="{{ route('work-orders.create') }}" icon="plus">New Work Order</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($workOrders->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="truck" title="No Work Orders Yet"
                description="A work order is a scheduled job: when it happens, who is assigned, where, and what it costs. Completing one can generate an invoice for the customer to pay."
                :steps="[
                    'Create a work order, or convert one from a ticket or service request.',
                    'Set the schedule, assignee, and line items for the services performed.',
                    'Mark it complete to generate an invoice from its line items.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('work-orders.create') }}">Create Your First Work Order</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                confirming: false,
                allIds: [{{ $workOrders->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('work-orders.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:toolbar>
                    <x-segmented label="Work Order Status">
                        @foreach ($tabs as $tab)
                            <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                                @if ($tab['active']) aria-current="page" @endif>
                                {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </x-segmented>
                </x-slot:toolbar>

                <x-slot:search>
                    <form method="GET" action="{{ route('work-orders.index') }}" class="flex flex-wrap items-center gap-2">
                        @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                        <label for="wo-upcoming" class="sr-only">Sort By Upcoming</label>
                        <select id="wo-upcoming" name="upcoming" onchange="this.form.submit()"
                            class="rounded-lg border-0 bg-white py-1.5 pl-3 pr-8 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            <option value="">Newest First</option>
                            <option value="1" @selected(! empty($filters['upcoming']))>Upcoming First</option>
                        </select>
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="wo-search" class="sr-only">Search Work Orders</label>
                            <input id="wo-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Number, Title, Or Customer"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-56">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('work-orders.index', array_filter(['status' => $filters['status'] ?? '', 'upcoming' => $filters['upcoming'] ?? ''])) }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-work-orders')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($workOrders->isEmpty())
                    <x-empty-state icon="search" title="No Work Orders Match These Filters"
                        description="Nothing here fits the current tab and search. Widen the search or switch back to All.">
                        <x-slot:action>
                            <x-button href="{{ route('work-orders.index') }}" variant="secondary" size="sm">Show All Work Orders</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Work Order</th>
                                <th>Customer</th>
                                <th>Scheduled</th>
                                <th>Assignee</th>
                                <th>Status</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($workOrders as $workOrder)
                                <tr class="vx-rail vx-rail-{{ $workOrder->status_badge }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $workOrder->id])</td>
                                    <td>
                                        <a href="{{ route('work-orders.show', $workOrder) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $workOrder->number }}</a>
                                        <span class="block max-w-xs truncate text-xs text-slate-500">{{ $workOrder->title }}</span>
                                    </td>
                                    <td class="text-slate-600">{{ $workOrder->customer?->name ?? 'No Customer' }}</td>
                                    <td class="text-slate-600">{{ $workOrder->scheduled_at?->format(config('shop.date_format', 'M j, Y')) ?? 'Not scheduled' }}</td>
                                    <td class="text-slate-600">{{ $workOrder->assignee?->name ?? 'Unassigned' }}</td>
                                    <td><x-badge :color="$workOrder->status_badge" dot>{{ $workOrder->status_label }}</x-badge></td>
                                    <td class="text-right font-semibold text-slate-900">{{ $workOrder->subtotal_formatted }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-work-orders" title="Delete Selected Work Orders?" icon="warning" tone="danger" maxWidth="max-w-md">
                This permanently removes the selected work orders and their line items. Any invoices already generated keep their history. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-work-orders')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-work-orders')">Delete Work Orders</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $workOrders->links() }}</div>
    @endif
</x-layouts.app>
