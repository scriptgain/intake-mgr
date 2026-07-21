<x-layouts.app :title="$customer->name">
    <x-page-header
        eyebrow="Customer"
        :title="$customer->name"
        icon="user"
        :subtitle="$customer->email"
        :back="['href' => route('customers.index'), 'label' => 'All Customers']">
        <x-slot:meta>
            <x-badge :color="$customer->has_account ? 'success' : 'neutral'" dot>{{ $customer->has_account ? 'Registered Account' : 'Guest Checkout' }}</x-badge>
            <x-badge :color="$customer->accepts_marketing ? 'info' : 'neutral'">{{ $customer->accepts_marketing ? 'Subscribed To Marketing' : 'Not Subscribed' }}</x-badge>
        </x-slot:meta>
        <x-slot:primary>
            <x-button href="{{ route('customers.edit', $customer) }}" icon="edit" size="sm">Edit Customer</x-button>
        </x-slot:primary>
    </x-page-header>

    {{-- Lifetime value reads as one connected picture, so these sit on a single
         hairline-divided surface rather than three floating tiles. --}}
    <dl class="mb-6 grid grid-cols-1 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 sm:grid-cols-3 sm:divide-x sm:divide-slate-200">
        <div class="border-b border-slate-200 px-5 py-4 sm:border-b-0">
            <dt class="vx-eyebrow">Total Spent</dt>
            <dd class="mt-1.5"><x-money :formatted="$customer->total_spent_formatted" size="lg" class="text-slate-900" /></dd>
        </div>
        <div class="border-b border-slate-200 px-5 py-4 sm:border-b-0">
            <dt class="vx-eyebrow">Orders</dt>
            <dd class="tabular mt-1.5 text-2xl font-semibold text-slate-900">{{ $customer->orders_count }}</dd>
        </div>
        <div class="px-5 py-4">
            <dt class="vx-eyebrow">Last Order</dt>
            <dd class="mt-1.5 text-lg font-medium text-slate-900">{{ $customer->last_order_at?->diffForHumans() ?? 'Never' }}</dd>
        </div>
    </dl>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Order History" flush>
                @if ($orders->isEmpty())
                    <x-empty-state icon="bag" title="No Orders From This Customer Yet"
                        description="This record exists because they registered an account. Once they buy something, every order shows up here with its payment and fulfillment status." />
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Order</th><th>Payment</th><th>Fulfillment</th><th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr class="vx-rail vx-rail-{{ $order->financial_status === 'pending' ? 'danger' : $order->fulfillment_badge }}">
                                    <td>
                                        <a href="{{ route('orders.show', $order) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $order->number }}</a>
                                        <span class="block text-xs text-slate-500">{{ $order->created_at?->diffForHumans() }}</span>
                                    </td>
                                    <td><x-badge :color="$order->financial_badge" dot>{{ \Illuminate\Support\Str::headline($order->financial_status) }}</x-badge></td>
                                    <td><x-badge :color="$order->fulfillment_badge" dot>{{ \Illuminate\Support\Str::headline($order->fulfillment_status) }}</x-badge></td>
                                    <td class="text-right font-semibold text-slate-900">{{ $order->total_formatted }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                    <div class="border-t border-slate-100 p-4">{{ $orders->links() }}</div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Contact">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="vx-eyebrow">Email</dt>
                        <dd class="mt-1"><a href="mailto:{{ $customer->email }}" class="break-all text-slate-900 hover:text-brand-700">{{ $customer->email }}</a></dd>
                    </div>
                    <div>
                        <dt class="vx-eyebrow">Phone</dt>
                        <dd class="mt-1 text-slate-900">{{ $customer->phone ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="vx-eyebrow">Marketing</dt>
                        <dd class="mt-1 text-slate-900">{{ $customer->accepts_marketing ? 'Subscribed' : 'Not Subscribed' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Saved Addresses">
                @if ($customer->addresses->isEmpty())
                    <p class="text-sm text-slate-500">No saved addresses. Guests who check out without an account do not save one.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($customer->addresses as $address)
                            <div class="border-b border-slate-100 pb-4 text-sm text-slate-600 last:border-0 last:pb-0">
                                <p class="flex items-center gap-2 font-medium text-slate-900">
                                    {{ $address->label ?: 'Address' }}
                                    @if ($address->is_default)<x-badge color="info">Default</x-badge>@endif
                                </p>
                                <p class="mt-1">{{ $address->summary }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            @if ($customer->notes)
                <x-card title="Notes" subtitle="Internal. The customer never sees these.">
                    <p class="whitespace-pre-line text-sm text-slate-600">{{ $customer->notes }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
