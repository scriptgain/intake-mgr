<x-layouts.app :title="$ticket->number">
    <x-page-header
        eyebrow="Ticket"
        :title="$ticket->number"
        icon="envelope"
        :subtitle="$ticket->subject"
        :back="['href' => route('tickets.index'), 'label' => 'All Tickets']">
        <x-slot:meta>
            <x-badge :color="$ticket->status_badge" dot>{{ $ticket->status_label }}</x-badge>
            <x-badge :color="$ticket->priority_badge" dot>{{ $ticket->priority_label }} Priority</x-badge>
            @if ($ticket->assignee)
                <x-badge color="neutral">{{ $ticket->assignee->name }}</x-badge>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            <x-confirm-action name="ticket-work-order" :action="route('tickets.work-order', $ticket)"
                title="Create Work Order From This Ticket?" tone="default" confirm="Create Work Order" confirmIcon="truck"
                message="A new work order is scheduled for this customer, linked back to this ticket. You set the schedule and line items on the work order.">
                <x-button variant="secondary" size="sm" icon="truck">Create Work Order</x-button>
            </x-confirm-action>
            <x-delete-button :action="route('tickets.destroy', $ticket)" name="delete-ticket"
                label="Delete Ticket" title="Delete This Ticket?"
                message="This permanently removes the ticket and its replies. This cannot be undone." />
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">
            {{-- Original request --}}
            <x-card :title="$ticket->subject" subtitle="Opened {{ $ticket->created_at?->diffForHumans() }}">
                @if ($ticket->description)
                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $ticket->description }}</p>
                @else
                    <p class="text-sm text-slate-500">No description was provided.</p>
                @endif
            </x-card>

            {{-- Thread --}}
            <x-card title="Conversation" subtitle="Staff replies, customer replies, and internal notes in order.">
                @if ($ticket->replies->isEmpty())
                    <x-empty-state icon="envelope" title="No Replies Yet"
                        description="Reply below to start the conversation, or add an internal note that only staff can see." />
                @else
                    <div class="space-y-4">
                        @foreach ($ticket->replies as $reply)
                            <div @class([
                                'rounded-xl p-4 ring-1 ring-inset',
                                'bg-amber-50 ring-amber-200' => $reply->is_internal,
                                'bg-brand-50/60 ring-brand-100' => ! $reply->is_internal && $reply->is_staff,
                                'bg-slate-50 ring-slate-200' => ! $reply->is_internal && ! $reply->is_staff,
                            ])>
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-slate-900">{{ $reply->author_label }}</span>
                                        @if ($reply->is_internal)
                                            <x-badge color="warn" dot>Internal Note</x-badge>
                                        @elseif ($reply->is_staff)
                                            <x-badge color="info">Staff</x-badge>
                                        @else
                                            <x-badge color="neutral">Customer</x-badge>
                                        @endif
                                    </div>
                                    <span class="text-xs text-slate-500">{{ $reply->created_at?->diffForHumans() }}</span>
                                </div>
                                <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $reply->body }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- Reply form --}}
            <x-card title="Add A Reply">
                <form method="POST" action="{{ route('tickets.reply', $ticket) }}" class="space-y-4">
                    @csrf
                    <x-field label="Message" for="reply-body" required :error="$errors->first('body')">
                        <textarea id="reply-body" name="body" rows="4" required placeholder="Type your reply to the customer, or an internal note for staff"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('body') }}</textarea>
                    </x-field>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                        <x-toggle name="is_internal" label="Internal Note" description="Only staff see this. The customer is never notified." />
                        <x-button type="submit" icon="envelope">Send Reply</x-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Activity">
                @include('admin._activity-timeline', ['activities' => $ticket->activities])
            </x-card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <x-card title="Status">
                <form method="POST" action="{{ route('tickets.status', $ticket) }}" class="space-y-3">
                    @csrf
                    <x-field label="Ticket Status" for="ticket-status">
                        <x-select id="ticket-status" name="status">
                            @foreach (\App\Models\Ticket::STATUSES as $status)
                                <option value="{{ $status }}" @selected($ticket->status === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <div class="flex justify-end">
                        <x-button type="submit" size="sm" icon="refresh">Update Status</x-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Assignee">
                <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="space-y-3">
                    @csrf
                    <x-field label="Assigned Agent" for="ticket-assignee">
                        <x-select id="ticket-assignee" name="assigned_user_id">
                            <option value="">Unassigned</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected($ticket->assigned_user_id === $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <div class="flex justify-end">
                        <x-button type="submit" size="sm" icon="user">Assign</x-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Customer">
                @if ($ticket->customer)
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-brand-200">
                            {{ $ticket->customer->initials }}
                        </span>
                        <div class="min-w-0">
                            <a href="{{ route('customers.show', $ticket->customer) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $ticket->customer->name }}</a>
                            <a href="mailto:{{ $ticket->customer->email }}" class="block truncate text-xs text-slate-500 hover:text-brand-700">{{ $ticket->customer->email }}</a>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-slate-500">No customer is linked to this ticket.</p>
                @endif

                @if ($ticket->serviceRequest || $ticket->project)
                    <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                        @if ($ticket->serviceRequest)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Request</dt>
                                <dd><a href="{{ route('service-requests.show', $ticket->serviceRequest) }}" class="font-medium text-brand-700 hover:underline">{{ $ticket->serviceRequest->number }}</a></dd>
                            </div>
                        @endif
                        @if ($ticket->project)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Project</dt>
                                <dd><a href="{{ route('projects.show', $ticket->project) }}" class="font-medium text-brand-700 hover:underline">{{ $ticket->project->number }}</a></dd>
                            </div>
                        @endif
                    </dl>
                @endif
            </x-card>

            <x-card title="Work Orders" flush>
                @if ($ticket->workOrders->isEmpty())
                    <x-empty-state icon="truck" title="No Work Orders"
                        description="Use Create Work Order above to schedule a visit for this ticket." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($ticket->workOrders as $workOrder)
                            <li class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6">
                                <div class="min-w-0">
                                    <a href="{{ route('work-orders.show', $workOrder) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $workOrder->number }}</a>
                                    <span class="block truncate text-xs text-slate-500">{{ $workOrder->title }}</span>
                                </div>
                                <x-badge :color="$workOrder->status_badge" dot>{{ $workOrder->status_label }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @if ($ticket->attachments->isNotEmpty())
                <x-card title="Attachments" flush>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($ticket->attachments as $file)
                            <li class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6">
                                <span class="min-w-0 truncate text-sm text-slate-700">{{ $file->filename }}</span>
                                <span class="shrink-0 text-xs text-slate-400">{{ $file->size_formatted }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
