<x-layouts.app title="Orders">
    <x-page-header
        eyebrow="Sales"
        title="Orders"
        icon="credit-card"
        subtitle="Every sale, its payment, and where it is in fulfillment." />

    @if ($orders->isEmpty() && ! array_filter($filters))
        <x-card>
            <x-empty-state icon="credit-card" title="No Orders Yet"
                description="When a shopper checks out, the order appears here with its payment and fulfillment status. You work orders from this screen: take payment, ship items, add tracking, and issue refunds."
                :steps="[
                    'Publish a product so the storefront has something to sell.',
                    'Add a shipping zone and rate so checkout can quote delivery.',
                    'Place a test order to confirm payment and fulfillment work end to end.',
                ]">
                <x-slot:action>
                    <x-button href="{{ route('shipping.index') }}" size="sm" icon="truck">Set Up Shipping</x-button>
                    <x-button href="{{ route('shop.home') }}" target="_blank" rel="noopener" variant="secondary" size="sm" icon="external">Open Storefront</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                confirming: false,
                allIds: [{{ $orders->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('orders.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                {{-- Filters ride the top edge of the table's own surface rather
                     than stacking as two more rows above it. --}}
                <x-slot:toolbar>
                    <x-segmented label="Order Status">
                        @foreach ($tabs as $tab)
                            <a href="{{ $tab['href'] }}" class="vx-seg-item {{ $tab['active'] ? 'is-active' : '' }}"
                                @if ($tab['active']) aria-current="page" @endif>
                                {{ $tab['label'] }} <span class="vx-seg-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </x-segmented>
                </x-slot:toolbar>

                <x-slot:search>
                    <form method="GET" action="{{ route('orders.index') }}" class="flex flex-wrap items-center gap-2">
                        @foreach (['status', 'financial', 'fulfillment'] as $preserve)
                            @if (! empty($filters[$preserve]))<input type="hidden" name="{{ $preserve }}" value="{{ $filters[$preserve] }}">@endif
                        @endforeach
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="order-search" class="sr-only">Search Orders</label>
                            <input id="order-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Order #, Email, Or SKU"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-64">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('orders.index', array_filter(\Illuminate\Support\Arr::only($filters, ['status', 'financial', 'fulfillment']))) }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="archive"
                                x-on:click="$dispatch('open-modal', 'bulk-archive-orders')">Archive Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($orders->isEmpty())
                    <x-empty-state icon="search" title="No Orders Match These Filters"
                        description="Nothing here fits the current tab and search. Widen the search or switch back to All.">
                        <x-slot:action>
                            <x-button href="{{ route('orders.index') }}" variant="secondary" size="sm">Show All Orders</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Order</th>
                                <th>Customer</th>
                                <th class="text-right">Items</th>
                                <th>Payment</th>
                                <th>Fulfillment</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                {{-- Rail keys off payment first: money not collected outranks
                                     an unshipped box. --}}
                                <tr class="vx-rail vx-rail-{{ $order->financial_status === 'pending' ? 'danger' : $order->fulfillment_badge }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $order->id])</td>
                                    <td>
                                        <a href="{{ route('orders.show', $order) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $order->number }}</a>
                                        <span class="block text-xs text-slate-500">{{ $order->created_at?->diffForHumans() }}</span>
                                    </td>
                                    <td class="text-slate-600">{{ $order->customer_name }}</td>
                                    <td class="tabular text-right">{{ $order->item_count }}</td>
                                    <td><x-badge :color="$order->financial_badge" dot>{{ \Illuminate\Support\Str::headline($order->financial_status) }}</x-badge></td>
                                    <td><x-badge :color="$order->fulfillment_badge" dot>{{ \Illuminate\Support\Str::headline($order->fulfillment_status) }}</x-badge></td>
                                    <td class="text-right font-semibold text-slate-900">{{ $order->total_formatted }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-archive-orders" title="Archive Selected Orders?" icon="archive" tone="warn" maxWidth="max-w-md">
                Archived orders drop out of the working list and out of the fulfillment queue. They stay in reporting and can still be opened directly.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-archive-orders')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="archive"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-archive-orders')">Archive Orders</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-layouts.app>
