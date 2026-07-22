<x-layouts.app title="Tickets">
    <x-page-header
        eyebrow="Service Desk"
        title="Tickets"
        icon="envelope"
        subtitle="Every service-desk conversation, with its status, priority, and owner." />

    @if ($tickets->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="envelope" title="No Tickets Yet"
                description="A ticket is the running conversation with a customer about a problem or request. Convert a service request into one, or a ticket is opened directly, and the whole thread lives here."
                :steps="[
                    'Open a service request and convert it to a ticket.',
                    'Reply to the customer, or add an internal note the customer never sees.',
                    'Change status as the work progresses, and spawn a work order when a visit is needed.',
                ]" />
        </x-card>
    @else
        <div x-data="{
                selected: [],
                confirming: false,
                allIds: [{{ $tickets->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('tickets.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:toolbar>
                    <x-segmented label="Ticket Status">
                        @foreach ($tabs as $tab)
                            <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                                @if ($tab['active']) aria-current="page" @endif>
                                {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </x-segmented>
                </x-slot:toolbar>

                <x-slot:search>
                    <form method="GET" action="{{ route('tickets.index') }}" class="flex flex-wrap items-center gap-2">
                        @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="ticket-search" class="sr-only">Search Tickets</label>
                            <input id="ticket-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Number, Subject, Or Customer"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-56">
                        </div>
                        <label for="ticket-priority" class="sr-only">Filter By Priority</label>
                        <select id="ticket-priority" name="priority" onchange="this.form.submit()"
                            class="rounded-lg border-0 bg-white py-1.5 pl-3 pr-8 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            <option value="">All Priorities</option>
                            @foreach (\App\Models\Ticket::PRIORITIES as $priority)
                                <option value="{{ $priority }}" @selected(($filters['priority'] ?? null) === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                            @endforeach
                        </select>
                        <label for="ticket-assignee" class="sr-only">Filter By Assignee</label>
                        <select id="ticket-assignee" name="assignee" onchange="this.form.submit()"
                            class="rounded-lg border-0 bg-white py-1.5 pl-3 pr-8 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            <option value="">All Agents</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected((string) ($filters['assignee'] ?? '') === (string) $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </select>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']) || ! empty($filters['priority']) || ! empty($filters['assignee']))
                            <x-button variant="ghost" size="sm" href="{{ route('tickets.index', array_filter(['status' => $filters['status'] ?? ''])) }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-tickets')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($tickets->isEmpty())
                    <x-empty-state icon="search" title="No Tickets Match These Filters"
                        description="Nothing here fits the current tab and filters together. Try clearing one of them.">
                        <x-slot:action>
                            <x-button href="{{ route('tickets.index') }}" variant="secondary" size="sm">Show All Tickets</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Ticket</th>
                                <th>Customer</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assignee</th>
                                <th>Last Reply</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                <tr class="vx-rail vx-rail-{{ $ticket->priority_badge }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $ticket->id])</td>
                                    <td>
                                        <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $ticket->number }}</a>
                                        <span class="block max-w-xs truncate text-xs text-slate-500">{{ $ticket->subject }}</span>
                                    </td>
                                    <td class="text-slate-600">{{ $ticket->customer?->name ?? 'No Customer' }}</td>
                                    <td><x-badge :color="$ticket->priority_badge" dot>{{ $ticket->priority_label }}</x-badge></td>
                                    <td><x-badge :color="$ticket->status_badge" dot>{{ $ticket->status_label }}</x-badge></td>
                                    <td class="text-slate-600">{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                                    <td class="text-slate-500">{{ $ticket->last_reply_at?->diffForHumans() ?? 'No replies' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-tickets" title="Delete Selected Tickets?" icon="warning" tone="danger" maxWidth="max-w-md">
                This permanently removes the selected tickets and their replies. Linked work orders keep their history. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-tickets')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-tickets')">Delete Tickets</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $tickets->links() }}</div>
    @endif
</x-layouts.app>
