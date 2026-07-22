<x-layouts.app :title="$serviceRequest->number">
    <x-page-header
        eyebrow="Service Request"
        :title="$serviceRequest->number"
        icon="bell"
        :subtitle="'Received ' . $serviceRequest->created_at?->diffForHumans() . ' via ' . \Illuminate\Support\Str::headline($serviceRequest->source)"
        :back="['href' => route('service-requests.index'), 'label' => 'All Requests']">
        <x-slot:meta>
            <x-badge :color="$serviceRequest->status_badge" dot>{{ $serviceRequest->status_label }}</x-badge>
            <x-badge :color="$serviceRequest->priority_badge" dot>{{ \Illuminate\Support\Str::headline($serviceRequest->priority) }} Priority</x-badge>
        </x-slot:meta>

        <x-slot:actions>
            @if ($serviceRequest->is_open)
                <x-confirm-action name="close-request" :action="route('service-requests.close', $serviceRequest)"
                    title="Close This Request?" tone="warn" confirm="Close Request" confirmIcon="x-circle" confirmVariant="secondary"
                    message="The request moves to Closed and leaves the triage inbox. You can still open it directly later.">
                    <x-button variant="secondary" size="sm" icon="x-circle">Close</x-button>
                </x-confirm-action>
            @endif
        </x-slot:actions>

        <x-slot:primary>
            @if (! $serviceRequest->ticket_id)
                <x-confirm-action name="convert-ticket" :action="route('service-requests.convert-ticket', $serviceRequest)"
                    title="Convert To Ticket?" tone="default" confirm="Create Ticket" confirmIcon="envelope"
                    message="A new ticket is opened with this request's subject, description, and requester. The request is marked Converted.">
                    <x-button size="sm" icon="envelope">Convert To Ticket</x-button>
                </x-confirm-action>
            @endif
        </x-slot:primary>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">
            <x-card :title="$serviceRequest->subject" subtitle="What the requester asked for.">
                @if ($serviceRequest->description)
                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $serviceRequest->description }}</p>
                @else
                    <p class="text-sm text-slate-500">No description was provided.</p>
                @endif

                <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="vx-eyebrow mb-1">Service</dt>
                        <dd class="text-slate-900">{{ $serviceRequest->service?->name ?? 'Not specified' }}</dd>
                    </div>
                    <div>
                        <dt class="vx-eyebrow mb-1">Source</dt>
                        <dd class="text-slate-900">{{ \Illuminate\Support\Str::headline($serviceRequest->source) }}</dd>
                    </div>
                    @if (! empty($addressLines))
                        <div class="sm:col-span-2">
                            <dt class="vx-eyebrow mb-1">Service Address</dt>
                            <dd>
                                <address class="space-y-0.5 not-italic text-slate-700">
                                    @foreach ($addressLines as $line)<p>{{ $line }}</p>@endforeach
                                </address>
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            <x-card title="Attachments" flush>
                @if ($serviceRequest->attachments->isEmpty())
                    <x-empty-state icon="folder" title="No Attachments"
                        description="Photos or documents sent with this request would be listed here." />
                @else
                    <x-table flush>
                        <thead>
                            <tr><th>File</th><th>Type</th><th class="text-right">Size</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($serviceRequest->attachments as $file)
                                <tr>
                                    <td class="font-medium text-slate-900">{{ $file->filename }}</td>
                                    <td class="text-slate-500">{{ $file->mime ?: 'Unknown' }}</td>
                                    <td class="text-right text-slate-500">{{ $file->size_formatted }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>

            <x-card title="Activity">
                @include('admin._activity-timeline', ['activities' => $serviceRequest->activities])
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Requester">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-brand-200">
                        {{ $serviceRequest->customer?->initials ?? mb_strtoupper(mb_substr($serviceRequest->name, 0, 2)) }}
                    </span>
                    <div class="min-w-0">
                        @if ($serviceRequest->customer)
                            <a href="{{ route('customers.show', $serviceRequest->customer) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $serviceRequest->name }}</a>
                        @else
                            <span class="block truncate font-medium text-slate-900">{{ $serviceRequest->name }}</span>
                        @endif
                        <a href="mailto:{{ $serviceRequest->email }}" class="block truncate text-xs text-slate-500 hover:text-brand-700">{{ $serviceRequest->email }}</a>
                    </div>
                </div>
                @if ($serviceRequest->phone)<p class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-600">{{ $serviceRequest->phone }}</p>@endif
            </x-card>

            <x-card title="Convert">
                <div class="space-y-3">
                    @if ($serviceRequest->ticket_id)
                        <a href="{{ route('tickets.show', $serviceRequest->ticket_id) }}" class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2.5 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-200 hover:bg-emerald-100">
                            <x-icon name="check-circle" class="h-4 w-4 shrink-0" /> View Linked Ticket
                        </a>
                    @else
                        <x-confirm-action name="convert-ticket-side" :action="route('service-requests.convert-ticket', $serviceRequest)"
                            title="Convert To Ticket?" tone="default" confirm="Create Ticket" confirmIcon="envelope"
                            message="A new ticket is opened with this request's details and the request is marked Converted.">
                            <x-button variant="secondary" size="sm" icon="envelope" class="w-full">Convert To Ticket</x-button>
                        </x-confirm-action>
                    @endif

                    @if ($serviceRequest->work_order_id)
                        <a href="{{ route('work-orders.show', $serviceRequest->work_order_id) }}" class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2.5 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-200 hover:bg-emerald-100">
                            <x-icon name="check-circle" class="h-4 w-4 shrink-0" /> View Linked Work Order
                        </a>
                    @else
                        <x-confirm-action name="convert-wo-side" :action="route('service-requests.convert-work-order', $serviceRequest)"
                            title="Convert To Work Order?" tone="default" confirm="Create Work Order" confirmIcon="truck"
                            message="A new work order is scheduled with this request's details and the request is marked Converted.">
                            <x-button variant="secondary" size="sm" icon="truck" class="w-full">Convert To Work Order</x-button>
                        </x-confirm-action>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>
