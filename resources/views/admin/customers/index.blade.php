<x-layouts.app title="Customers">
    <x-page-header
        eyebrow="Sales"
        title="Customers"
        icon="users"
        subtitle="Everyone who has bought from, or signed up for, your store." />

    @if ($customers->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="users" title="No Customers Yet"
                description="A customer record is created automatically the first time someone checks out, whether they registered an account or bought as a guest. You do not add them by hand. Each record collects order history and total spend, so repeat buyers are easy to spot." />
        </x-card>
    @else
        <div x-data="{
                selected: [],
                allIds: [{{ $customers->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('customers.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:toolbar>
                    <x-segmented label="Customer Segments">
                        @foreach ($tabs as $tab)
                            <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                                @if ($tab['active']) aria-current="page" @endif>
                                {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </x-segmented>
                </x-slot:toolbar>

                <x-slot:search>
                    <form method="GET" action="{{ route('customers.index') }}" class="flex flex-wrap items-center gap-2">
                        @if (! empty($filters['filter']))<input type="hidden" name="filter" value="{{ $filters['filter'] }}">@endif
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="customer-search" class="sr-only">Search Customers</label>
                            <input id="customer-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Name, Email, Or Phone"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-64">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('customers.index', array_filter(['filter' => $filters['filter'] ?? ''])) }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-customers')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($customers->isEmpty())
                    <x-empty-state icon="search" title="No Customers Match This Search"
                        description="Nothing here fits the current segment and search together.">
                        <x-slot:action>
                            <x-button href="{{ route('customers.index') }}" variant="secondary" size="sm">Show All Customers</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Customer</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Total Spent</th>
                                <th>Last Order</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $customer)
                                <tr>
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $customer->id])</td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-brand-200">{{ $customer->initials }}</span>
                                            <div class="min-w-0">
                                                <a href="{{ route('customers.show', $customer) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $customer->name }}</a>
                                                <span class="block truncate text-xs text-slate-500">{{ $customer->email }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="tabular text-right">{{ $customer->orders_count }}</td>
                                    <td class="text-right font-semibold text-slate-900">{{ $customer->total_spent_formatted }}</td>
                                    <td class="text-slate-500">{{ $customer->last_order_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-button href="{{ route('customers.show', $customer) }}" icon="eye" title="View Customer" />
                                            <x-icon-button href="{{ route('customers.edit', $customer) }}" icon="edit" title="Edit Customer" />
                                            <x-delete-button :action="route('customers.destroy', $customer)" name="del-customer-{{ $customer->id }}"
                                                label="Delete Customer"
                                                title="Delete This Customer?"
                                                :message="'This removes the record for ' . $customer->name . '. Their past orders stay in your reporting and keep the name and email captured at checkout.'" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-customers" title="Delete Selected Customers?" icon="warning" tone="danger" maxWidth="max-w-md">
                This removes the selected customer records. Their past orders stay in your reporting and keep the name and email captured at checkout. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-customers')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-customers')">Delete Customers</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $customers->links() }}</div>
    @endif
</x-layouts.app>
