<x-layouts.app :title="$project->number">
    <x-page-header
        eyebrow="Project"
        :title="$project->number"
        icon="folder"
        :subtitle="$project->name"
        :back="['href' => route('projects.index'), 'label' => 'All Projects']">
        <x-slot:meta>
            <x-badge :color="$project->status_badge" dot>{{ $project->status_label }}</x-badge>
            @if ($project->customer)
                <x-badge color="neutral">{{ $project->customer->name }}</x-badge>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="edit" href="{{ route('projects.edit', $project) }}">Edit</x-button>
            <x-delete-button :action="route('projects.destroy', $project)" name="delete-project"
                label="Delete Project" title="Delete This Project?"
                message="This permanently removes the project. Its tickets and work orders are kept and unlinked. This cannot be undone." />
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">
            <x-card title="Progress" subtitle="Share of this project's work orders that are completed.">
                <div class="flex items-center gap-4">
                    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100 ring-1 ring-inset ring-slate-200">
                        <div class="h-full rounded-full bg-brand-600 transition-all" style="width: {{ $project->progress_percent }}%"></div>
                    </div>
                    <span class="tabular shrink-0 text-sm font-semibold text-slate-900">{{ $project->progress_percent }}%</span>
                </div>
            </x-card>

            @if ($project->description)
                <x-card title="Description">
                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $project->description }}</p>
                </x-card>
            @endif

            <x-card title="Tickets" flush>
                @if ($project->tickets->isEmpty())
                    <x-empty-state icon="envelope" title="No Tickets Linked"
                        description="Tickets that belong to this project appear here." />
                @else
                    <x-table flush>
                        <thead>
                            <tr><th>Ticket</th><th>Status</th><th>Assignee</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($project->tickets as $ticket)
                                <tr>
                                    <td>
                                        <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $ticket->number }}</a>
                                        <span class="block max-w-xs truncate text-xs text-slate-500">{{ $ticket->subject }}</span>
                                    </td>
                                    <td><x-badge :color="$ticket->status_badge" dot>{{ $ticket->status_label }}</x-badge></td>
                                    <td class="text-slate-600">{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>

            <x-card title="Work Orders" flush>
                @if ($project->workOrders->isEmpty())
                    <x-empty-state icon="truck" title="No Work Orders Linked"
                        description="Scheduled jobs that belong to this project appear here." />
                @else
                    <x-table flush>
                        <thead>
                            <tr><th>Work Order</th><th>Status</th><th>Scheduled</th><th class="text-right">Total</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($project->workOrders as $workOrder)
                                <tr>
                                    <td>
                                        <a href="{{ route('work-orders.show', $workOrder) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $workOrder->number }}</a>
                                        <span class="block max-w-xs truncate text-xs text-slate-500">{{ $workOrder->title }}</span>
                                    </td>
                                    <td><x-badge :color="$workOrder->status_badge" dot>{{ $workOrder->status_label }}</x-badge></td>
                                    <td class="text-slate-600">{{ $workOrder->scheduled_at?->format(config('shop.date_format', 'M j, Y')) ?? 'Not scheduled' }}</td>
                                    <td class="text-right font-semibold text-slate-900">{{ $workOrder->subtotal_formatted }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>

            <x-card title="Activity">
                @include('admin._activity-timeline', ['activities' => $project->activities])
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Status">
                <form method="POST" action="{{ route('projects.status', $project) }}" class="space-y-3">
                    @csrf
                    <x-field label="Project Status" for="project-status">
                        <x-select id="project-status" name="status">
                            @foreach (\App\Models\Project::STATUSES as $status)
                                <option value="{{ $status }}" @selected($project->status === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <div class="flex justify-end">
                        <x-button type="submit" size="sm" icon="refresh">Update Status</x-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Timeline">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Starts</dt>
                        <dd class="text-slate-900">{{ $project->starts_on?->format(config('shop.date_format', 'M j, Y')) ?? 'Not set' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Due</dt>
                        <dd class="text-slate-900">{{ $project->due_on?->format(config('shop.date_format', 'M j, Y')) ?? 'Not set' }}</dd>
                    </div>
                    @if ($project->completed_at)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Completed</dt>
                            <dd class="text-slate-900">{{ $project->completed_at->format(config('shop.date_format', 'M j, Y')) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Owner</dt>
                        <dd class="text-slate-900">{{ $project->assignee?->name ?? 'Unassigned' }}</dd>
                    </div>
                </dl>
            </x-card>

            @if ($project->customer)
                <x-card title="Customer">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-brand-200">
                            {{ $project->customer->initials }}
                        </span>
                        <div class="min-w-0">
                            <a href="{{ route('customers.show', $project->customer) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $project->customer->name }}</a>
                            <a href="mailto:{{ $project->customer->email }}" class="block truncate text-xs text-slate-500 hover:text-brand-700">{{ $project->customer->email }}</a>
                        </div>
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
