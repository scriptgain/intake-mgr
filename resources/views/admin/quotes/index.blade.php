<x-layouts.app title="Quotes">
    <x-page-header
        eyebrow="Service Desk"
        title="Quotes"
        icon="document"
        subtitle="Priced estimates sent to customers. Send one, let them accept, then convert it to an invoice or a work order.">
        <x-slot:primary>
            <x-button href="{{ route('quotes.create') }}" icon="plus">New Quote</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($quotes->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="document" title="No Quotes Yet"
                description="A quote is a priced estimate you send before the work is won. The customer can accept or decline it online; an accepted quote converts into an invoice and a scheduled work order."
                :steps="[
                    'Create a quote with the services and prices you propose.',
                    'Send it — the customer reviews and accepts or declines online.',
                    'Convert an accepted quote into an invoice and a work order.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('quotes.create') }}">Create Your First Quote</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                allIds: [{{ $quotes->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('quotes.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:toolbar>
                    <x-segmented label="Quote Status">
                        @foreach ($tabs as $tab)
                            <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                                @if ($tab['active']) aria-current="page" @endif>
                                {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </x-segmented>
                </x-slot:toolbar>

                <x-slot:search>
                    <form method="GET" action="{{ route('quotes.index') }}" class="flex flex-wrap items-center gap-2">
                        @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="q-search" class="sr-only">Search Quotes</label>
                            <input id="q-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Number, Title, Or Customer"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-56">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('quotes.index', array_filter(['status' => $filters['status'] ?? ''])) }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-quotes')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($quotes->isEmpty())
                    <x-empty-state icon="search" title="No Quotes Match These Filters"
                        description="Nothing here fits the current tab and search. Widen the search or switch back to All.">
                        <x-slot:action>
                            <x-button href="{{ route('quotes.index') }}" variant="secondary" size="sm">Show All Quotes</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Quote</th>
                                <th>Customer</th>
                                <th>Valid Until</th>
                                <th>Status</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quotes as $quote)
                                <tr class="vx-rail vx-rail-{{ $quote->status_badge }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $quote->id])</td>
                                    <td>
                                        <a href="{{ route('quotes.show', $quote) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $quote->number }}</a>
                                        <span class="block max-w-xs truncate text-xs text-slate-500">{{ $quote->title }}</span>
                                    </td>
                                    <td class="text-slate-600">{{ $quote->customer?->name ?? 'No Customer' }}</td>
                                    <td class="text-slate-600">{{ $quote->valid_until?->format(config('shop.date_format', 'M j, Y')) ?? '—' }}</td>
                                    <td><x-badge :color="$quote->status_badge" dot>{{ $quote->status_label }}</x-badge></td>
                                    <td class="text-right font-semibold text-slate-900">{{ $quote->total_formatted }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-quotes" title="Delete Selected Quotes?" icon="warning" tone="danger" maxWidth="max-w-md">
                This permanently removes the selected quotes and their line items. Any invoices or work orders already generated keep their history. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-quotes')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-quotes')">Delete Quotes</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $quotes->links() }}</div>
    @endif
</x-layouts.app>
