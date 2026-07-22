<x-layouts.app title="Projects">
    <x-page-header
        eyebrow="Service Desk"
        title="Projects"
        icon="folder"
        subtitle="Engagements that group related tickets and work orders under one job.">
        <x-slot:primary>
            <x-button href="{{ route('projects.create') }}" icon="plus">New Project</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($projects->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="folder" title="No Projects Yet"
                description="A project bundles the tickets and work orders that belong to one larger job, like a pool remodel or a seasonal maintenance contract, so you can track the whole engagement in one place."
                :steps="[
                    'Create a project and give it a name and customer.',
                    'Link tickets and work orders to it as the job takes shape.',
                    'Watch progress fill in as its work orders are completed.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('projects.create') }}">Create Your First Project</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                confirming: false,
                allIds: [{{ $projects->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('projects.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:toolbar>
                    <x-segmented label="Project Status">
                        @foreach ($tabs as $tab)
                            <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                                @if ($tab['active']) aria-current="page" @endif>
                                {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </x-segmented>
                </x-slot:toolbar>

                <x-slot:search>
                    <form method="GET" action="{{ route('projects.index') }}" class="flex flex-wrap items-center gap-2">
                        @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="project-search" class="sr-only">Search Projects</label>
                            <input id="project-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Number, Name, Or Customer"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-64">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('projects.index', array_filter(['status' => $filters['status'] ?? ''])) }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-projects')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($projects->isEmpty())
                    <x-empty-state icon="search" title="No Projects Match These Filters"
                        description="Nothing here fits the current tab and search. Widen the search or switch back to All.">
                        <x-slot:action>
                            <x-button href="{{ route('projects.index') }}" variant="secondary" size="sm">Show All Projects</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Project</th>
                                <th>Customer</th>
                                <th class="text-right">Tickets</th>
                                <th class="text-right">Work Orders</th>
                                <th>Due</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                <tr class="vx-rail vx-rail-{{ $project->status_badge }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $project->id])</td>
                                    <td>
                                        <a href="{{ route('projects.show', $project) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $project->number }}</a>
                                        <span class="block max-w-xs truncate text-xs text-slate-500">{{ $project->name }}</span>
                                    </td>
                                    <td class="text-slate-600">{{ $project->customer?->name ?? 'No Customer' }}</td>
                                    <td class="tabular text-right text-slate-700">{{ $project->tickets_count }}</td>
                                    <td class="tabular text-right text-slate-700">{{ $project->work_orders_count }}</td>
                                    <td class="text-slate-600">{{ $project->due_on?->format(config('shop.date_format', 'M j, Y')) ?? 'No date' }}</td>
                                    <td><x-badge :color="$project->status_badge" dot>{{ $project->status_label }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-projects" title="Delete Selected Projects?" icon="warning" tone="danger" maxWidth="max-w-md">
                This permanently removes the selected projects. Their tickets and work orders are kept and simply unlinked. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-projects')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-projects')">Delete Projects</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $projects->links() }}</div>
    @endif
</x-layouts.app>
