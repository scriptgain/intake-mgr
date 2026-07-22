<x-layouts.app :title="$workOrder->number">
    <x-page-header
        eyebrow="Work Order"
        :title="$workOrder->number"
        icon="truck"
        :subtitle="$workOrder->title"
        :back="['href' => route('work-orders.index'), 'label' => 'All Work Orders']">
        <x-slot:meta>
            <x-badge :color="$workOrder->status_badge" dot>{{ $workOrder->status_label }}</x-badge>
            @if ($workOrder->scheduled_at)
                <x-badge color="neutral">{{ $workOrder->scheduled_at->format(config('shop.date_format', 'M j, Y').' '.config('shop.time_format', 'g:i A')) }}</x-badge>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="edit" href="{{ route('work-orders.edit', $workOrder) }}">Edit</x-button>
            <x-add-to-calendar :work-order="$workOrder" :ics-url="route('work-orders.ics', $workOrder)" />
            @if ($workOrder->status !== 'cancelled')
                <x-button variant="secondary" size="sm" icon="x-circle" x-data @click="$dispatch('open-modal', 'cancel-work-order')">Cancel</x-button>
            @endif
            <x-delete-button :action="route('work-orders.destroy', $workOrder)" name="delete-work-order"
                label="Delete Work Order" title="Delete This Work Order?"
                message="This permanently removes the work order and its line items. Any generated invoice keeps its history. This cannot be undone." />
        </x-slot:actions>

        <x-slot:primary>
            @if (! in_array($workOrder->status, ['completed', 'cancelled'], true))
                <x-confirm-action name="complete-work-order" :action="route('work-orders.complete', $workOrder)"
                    title="Complete This Work Order?" tone="default" confirm="Complete And Invoice" confirmIcon="check-circle"
                    message="The work order is marked completed. If it has a billable subtotal, an invoice is generated from its line items for the customer to pay.">
                    <x-button size="sm" icon="check-circle">Complete</x-button>
                </x-confirm-action>
            @endif
        </x-slot:primary>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">
            <x-card title="Line Items" flush>
                @if ($workOrder->items->isEmpty())
                    <x-empty-state icon="tag" title="No Line Items"
                        description="This work order has no services on it yet. Edit it to add the services performed and their prices." />
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($workOrder->items as $item)
                                <tr>
                                    <td>
                                        <span class="font-medium text-slate-900">{{ $item->name }}</span>
                                        @if ($item->service)<span class="block text-xs text-slate-400">{{ $item->service->name }}</span>@endif
                                    </td>
                                    <td class="tabular text-right">{{ $item->quantity }}</td>
                                    <td class="tabular text-right text-slate-500">{{ $item->unit_price_formatted }}</td>
                                    <td class="text-right font-semibold text-slate-900">{{ $item->total_formatted }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                    <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3.5 sm:px-6">
                        <span class="text-sm font-medium text-slate-900">Subtotal</span>
                        <span class="tabular text-base font-semibold text-slate-900">{{ $workOrder->subtotal_formatted }}</span>
                    </div>
                @endif
            </x-card>

            @if ($workOrder->notes)
                <x-card title="Notes">
                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $workOrder->notes }}</p>
                </x-card>
            @endif

            <x-card title="Activity">
                @include('admin._activity-timeline', ['activities' => $workOrder->activities])
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Status">
                <form method="POST" action="{{ route('work-orders.status', $workOrder) }}" class="space-y-3">
                    @csrf
                    <x-field label="Work Order Status" for="wo-status">
                        <x-select id="wo-status" name="status">
                            @foreach (\App\Models\WorkOrder::STATUSES as $status)
                                <option value="{{ $status }}" @selected($workOrder->status === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <div class="flex justify-end">
                        <x-button type="submit" size="sm" icon="refresh">Update Status</x-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Schedule">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Scheduled</dt>
                        <dd class="text-slate-900">{{ $workOrder->scheduled_at?->format(config('shop.date_format', 'M j, Y').' '.config('shop.time_format', 'g:i A')) ?? 'Not scheduled' }}</dd>
                    </div>
                    @if ($workOrder->duration_minutes)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Duration</dt>
                            <dd class="text-slate-900">{{ $workOrder->duration_minutes }} min</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Assignee</dt>
                        <dd class="text-slate-900">{{ $workOrder->assignee?->name ?? 'Unassigned' }}</dd>
                    </div>
                    @if ($workOrder->completed_at)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Completed</dt>
                            <dd class="text-slate-900">{{ $workOrder->completed_at->format(config('shop.date_format', 'M j, Y')) }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            <x-card title="Customer">
                @if ($workOrder->customer)
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-brand-200">
                            {{ $workOrder->customer->initials }}
                        </span>
                        <div class="min-w-0">
                            <a href="{{ route('customers.show', $workOrder->customer) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $workOrder->customer->name }}</a>
                            <a href="mailto:{{ $workOrder->customer->email }}" class="block truncate text-xs text-slate-500 hover:text-brand-700">{{ $workOrder->customer->email }}</a>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-slate-500">No customer is linked to this work order.</p>
                @endif
            </x-card>

            @if (! empty($addressLines))
                <x-card title="Service Address">
                    <address class="space-y-0.5 text-sm not-italic text-slate-600">
                        @foreach ($addressLines as $line)<p>{{ $line }}</p>@endforeach
                    </address>
                </x-card>
            @endif

            @if ($workOrder->ticket || $workOrder->project || $workOrder->invoice)
                <x-card title="Linked">
                    <dl class="space-y-2.5 text-sm">
                        @if ($workOrder->ticket)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Ticket</dt>
                                <dd><a href="{{ route('tickets.show', $workOrder->ticket) }}" class="font-medium text-brand-700 hover:underline">{{ $workOrder->ticket->number }}</a></dd>
                            </div>
                        @endif
                        @if ($workOrder->project)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Project</dt>
                                <dd><a href="{{ route('projects.show', $workOrder->project) }}" class="font-medium text-brand-700 hover:underline">{{ $workOrder->project->number }}</a></dd>
                            </div>
                        @endif
                        @if ($workOrder->invoice)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Invoice</dt>
                                <dd><a href="{{ route('orders.show', $workOrder->invoice) }}" class="font-medium text-brand-700 hover:underline">{{ $workOrder->invoice->number }}</a></dd>
                            </div>
                        @endif
                    </dl>
                </x-card>
            @endif
        </div>
    </div>

    <x-modal name="cancel-work-order" title="Cancel This Work Order?" icon="warning" tone="danger" maxWidth="max-w-md">
        The work order moves to Cancelled and drops out of the active schedule. This cannot be undone.
        <form id="cancel-work-order-form" method="POST" action="{{ route('work-orders.cancel', $workOrder) }}" class="mt-4">
            @csrf
            <x-field label="Reason" for="cancel-reason" hint="Optional. Shown in the timeline.">
                <x-input id="cancel-reason" name="reason" placeholder="Optional" />
            </x-field>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'cancel-work-order')">Keep Work Order</x-button>
            <x-button variant="danger" size="sm" type="submit" form="cancel-work-order-form" icon="x-circle">Cancel Work Order</x-button>
        </x-slot:footer>
    </x-modal>
</x-layouts.app>
