<x-layouts.app title="Service Requests">
    <x-page-header
        eyebrow="Service Desk"
        title="Service Requests"
        icon="bell"
        subtitle="The triage inbox. Read what came in, then convert it to a ticket or a work order." />

    @if ($requests->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="bell" title="No Service Requests Yet"
                description="When someone submits the 'Request Service' form, or a staff member logs a call, it lands here. You triage each one and convert it into a ticket, a scheduled work order, or both."
                :steps="[
                    'A request arrives from the public form or is logged by phone.',
                    'Open it, read the details, and check the attachments.',
                    'Convert it to a ticket for a conversation, or a work order to schedule the job.',
                ]" />
        </x-card>
    @else
        <div x-data="{
                selected: [],
                confirming: false,
                allIds: [{{ $requests->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('service-requests.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:toolbar>
                    <x-segmented label="Request Status">
                        @foreach ($tabs as $tab)
                            <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                                @if ($tab['active']) aria-current="page" @endif>
                                {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </x-segmented>
                </x-slot:toolbar>

                <x-slot:search>
                    <form method="GET" action="{{ route('service-requests.index') }}" class="flex flex-wrap items-center gap-2">
                        @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="request-search" class="sr-only">Search Requests</label>
                            <input id="request-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Number, Name, Email, Or Subject"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-72">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('service-requests.index', array_filter(['status' => $filters['status'] ?? ''])) }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-requests')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($requests->isEmpty())
                    <x-empty-state icon="search" title="No Requests Match These Filters"
                        description="Nothing here fits the current tab and search. Widen the search or switch back to All.">
                        <x-slot:action>
                            <x-button href="{{ route('service-requests.index') }}" variant="secondary" size="sm">Show All Requests</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Request</th>
                                <th>Requester</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $req)
                                <tr class="vx-rail vx-rail-{{ $req->priority_badge }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $req->id])</td>
                                    <td>
                                        <a href="{{ route('service-requests.show', $req) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $req->number }}</a>
                                        <span class="block max-w-xs truncate text-xs text-slate-500">{{ $req->subject }}</span>
                                    </td>
                                    <td class="text-slate-600">
                                        {{ $req->name }}
                                        <span class="block text-xs text-slate-400">{{ $req->email }}</span>
                                    </td>
                                    <td><x-badge :color="$req->priority_badge" dot>{{ \Illuminate\Support\Str::headline($req->priority) }}</x-badge></td>
                                    <td><x-badge :color="$req->status_badge" dot>{{ $req->status_label }}</x-badge></td>
                                    <td class="text-slate-500">{{ $req->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-requests" title="Delete Selected Requests?" icon="warning" tone="danger" maxWidth="max-w-md">
                This permanently removes the selected service requests. Any tickets or work orders already converted from them are unaffected. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-requests')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-requests')">Delete Requests</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $requests->links() }}</div>
    @endif
</x-layouts.app>
