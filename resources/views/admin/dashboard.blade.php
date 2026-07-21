<x-layouts.app title="Dashboard">
    <x-page-header
        eyebrow="Store Overview"
        title="Dashboard"
        icon="dashboard"
        subtitle="What sold, what needs shipping, and what is about to run out.">
        <x-slot:actions>
            <x-button href="{{ route('orders.index') }}" variant="secondary" size="sm" icon="credit-card">View Orders</x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Hero. One surface, one dominant number. The four figures the old layout
         gave equal weight are not equally important: 30-day revenue is the
         answer to "how is the store doing", so it gets the size and the chart,
         and the rest become a hairline-divided strip underneath it. --}}
    <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200" aria-labelledby="revenue-heading">
        <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-5 lg:items-center lg:gap-8">
            <div class="min-w-0 lg:col-span-2">
                <h2 id="revenue-heading" class="vx-eyebrow">Revenue, Last 30 Days</h2>
                <div class="mt-2.5 flex flex-wrap items-baseline gap-3">
                    <x-money :cents="$revenueMonthCents" size="display" class="text-slate-900" />
                    <span @class([
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset',
                        'bg-emerald-50 text-emerald-700 ring-emerald-200' => $revenueTrend['tone'] === 'success',
                        'bg-rose-50 text-rose-700 ring-rose-200' => $revenueTrend['tone'] === 'danger',
                        'bg-slate-100 text-slate-600 ring-slate-200' => $revenueTrend['tone'] === 'neutral',
                    ])>{{ $revenueTrend['label'] }}</span>
                </div>
                <p class="mt-1.5 text-sm text-slate-500">Net of refunds, versus the previous 30 days.</p>
            </div>

            {{-- Sparkline. Registered by public/js/shop-admin.js. --}}
            <div class="min-w-0 lg:col-span-3" x-data="dashboardSparkline(@js($salesSeries))" x-init="init()">
                {{-- relative plot so the hovered dot + value tooltip position over it --}}
                <div class="relative" x-ref="plot" @mousemove="onMove($event)" @mouseleave="onLeave()">
                    <svg viewBox="0 0 640 160" preserveAspectRatio="none" class="h-20 w-full sm:h-24" role="img" aria-label="Daily net revenue over the last 30 days">
                        <polygon :points="areaPoints" class="fill-brand-500/10"></polygon>
                        <polyline :points="linePoints" fill="none" class="stroke-brand-500" stroke-width="2" vector-effect="non-scaling-stroke"></polyline>
                    </svg>

                    {{-- Marker + value on hover --}}
                    <template x-if="hover >= 0">
                        <span class="pointer-events-none absolute z-10 h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-600 ring-2 ring-white shadow"
                              :style="`left:${points[hover].xPct}%;top:${points[hover].yPct}%`"></span>
                    </template>
                    <template x-if="hover >= 0">
                        <div class="pointer-events-none absolute z-20 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs text-white shadow-lg"
                             :style="`left:clamp(2.5rem, ${points[hover].xPct}%, calc(100% - 2.5rem));top:calc(${points[hover].yPct}% - 0.6rem)`">
                            <span class="font-semibold" x-text="money(points[hover].cents)"></span>
                            <span class="ml-1.5 text-slate-300" x-text="points[hover].date"></span>
                        </div>
                    </template>
                </div>
                <div class="mt-1 flex justify-between text-[11px] text-slate-400">
                    <span x-text="firstLabel"></span>
                    <span x-text="lastLabel"></span>
                </div>
            </div>
        </div>

        {{-- Supporting figures, subordinate by size and position. --}}
        <dl class="grid grid-cols-1 border-t border-slate-200 sm:grid-cols-3 sm:divide-x sm:divide-slate-200">
            <div class="border-b border-slate-200 px-5 py-4 sm:border-b-0 sm:px-6">
                <dt class="vx-eyebrow">Revenue Today</dt>
                <dd class="mt-1.5"><x-money :cents="$revenueTodayCents" size="lg" class="text-slate-900" /></dd>
            </div>
            <div class="border-b border-slate-200 px-5 py-4 sm:border-b-0 sm:px-6">
                <dt class="vx-eyebrow">Orders, Last 30 Days</dt>
                <dd class="tabular mt-1.5 text-2xl font-semibold text-slate-900">{{ $ordersMonth }}</dd>
            </div>
            <div class="px-5 py-4 sm:px-6">
                <dt class="vx-eyebrow">Average Order</dt>
                <dd class="mt-1.5"><x-money :cents="$avgOrderCents" size="lg" class="text-slate-900" /></dd>
            </div>
        </dl>
    </section>

    <div class="section-divider my-8"></div>

    {{-- Needs Attention. A worklist, not three more metric tiles: every row is
         a task with a verb, and rows with nothing to do are not rendered. --}}
    <section aria-labelledby="attention-heading">
        <h2 id="attention-heading" class="mb-3 text-sm font-semibold text-slate-900">Needs Attention</h2>
        @if (empty($worklist))
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                <x-empty-state icon="check-circle" title="Everything Is Caught Up"
                    description="No orders are waiting to ship, nothing is unpaid, and no tracked variant is below its low-stock threshold." />
            </div>
        @else
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($worklist as $item)
                    <li>
                        <a href="{{ $item['href'] }}"
                            class="vx-rail vx-rail-{{ $item['tone'] }} group flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md hover:ring-brand-300">
                            <span @class([
                                'inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg ring-1',
                                'bg-amber-50 text-amber-600 ring-amber-200' => $item['tone'] === 'warn',
                                'bg-rose-50 text-rose-600 ring-rose-200' => $item['tone'] === 'danger',
                                'bg-brand-50 text-brand-600 ring-brand-200' => $item['tone'] === 'info',
                            ])>
                                <x-icon :name="$item['icon']" class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="tabular text-2xl font-semibold leading-none text-slate-900">{{ $item['count'] }}</p>
                                <p class="mt-1.5 truncate text-sm text-slate-500">{{ $item['label'] }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-brand-700 opacity-0 transition group-hover:opacity-100">
                                {{ $item['action'] }}
                                <x-icon name="chevron-right" class="h-4 w-4" aria-hidden="true" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="section-divider my-8"></div>

    {{-- Detail panels. Tabs rather than four stacked tables, so the page stays
         about one screen instead of a long scroll. --}}
    <section x-data="{ tab: 'orders' }" aria-labelledby="activity-heading">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 id="activity-heading" class="text-sm font-semibold text-slate-900">Recent Activity</h2>
            <x-segmented label="Activity Panels">
                <button type="button" role="tab" :aria-selected="(tab === 'orders').toString()" @click="tab = 'orders'"
                    class="vx-seg-item" :class="tab === 'orders' && 'is-active'">Recent Orders</button>
                <button type="button" role="tab" :aria-selected="(tab === 'products').toString()" @click="tab = 'products'"
                    class="vx-seg-item" :class="tab === 'products' && 'is-active'">Top Products</button>
                <button type="button" role="tab" :aria-selected="(tab === 'lowstock').toString()" @click="tab = 'lowstock'"
                    class="vx-seg-item" :class="tab === 'lowstock' && 'is-active'">Low Stock</button>
                <button type="button" role="tab" :aria-selected="(tab === 'customers').toString()" @click="tab = 'customers'"
                    class="vx-seg-item" :class="tab === 'customers' && 'is-active'">New Customers</button>
            </x-segmented>
        </div>

        {{-- Recent Orders --}}
        <div x-show="tab === 'orders'" x-cloak>
            <x-card flush>
                @if ($recentOrders->isEmpty())
                    <x-empty-state icon="bag" title="No Orders Yet"
                        description="Every order placed on your storefront lands here first, newest at the top. This is the list you work down each morning."
                        :steps="[
                            'Publish at least one product so the storefront has something to sell.',
                            'Add a shipping zone and rate, or checkout cannot quote a delivery price.',
                            'Place a test order yourself to confirm the whole flow works end to end.',
                        ]">
                        <x-slot:action>
                            <x-button href="{{ route('shipping.index') }}" size="sm" icon="truck">Set Up Shipping</x-button>
                            <x-button href="{{ route('shop.home') }}" target="_blank" rel="noopener" variant="secondary" size="sm" icon="external">Open Storefront</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Order</th><th>Customer</th><th>Payment</th><th>Fulfillment</th><th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentOrders as $order)
                                <tr class="vx-rail vx-rail-{{ $order->fulfillment_badge }}">
                                    <td>
                                        <a href="{{ route('orders.show', $order) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $order->number }}</a>
                                        <span class="block text-xs text-slate-500">{{ $order->created_at?->diffForHumans() }}</span>
                                    </td>
                                    <td class="text-slate-600">{{ $order->customer_name }}</td>
                                    <td><x-badge :color="$order->financial_badge" dot>{{ \Illuminate\Support\Str::headline($order->financial_status) }}</x-badge></td>
                                    <td><x-badge :color="$order->fulfillment_badge" dot>{{ \Illuminate\Support\Str::headline($order->fulfillment_status) }}</x-badge></td>
                                    <td class="text-right font-medium text-slate-900">{{ $order->total_formatted }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        {{-- Top Products --}}
        <div x-show="tab === 'products'" x-cloak>
            <x-card flush>
                @if ($topProducts->isEmpty())
                    <x-empty-state icon="star" title="No Sales To Rank Yet"
                        description="Once orders start arriving, this ranks products by units sold over the last 30 days, so you can see what is carrying the store and what is not moving." />
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Product</th><th class="text-right">Units Sold</th><th class="text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topProducts as $row)
                                <tr>
                                    <td class="font-medium text-slate-900">
                                        @if ($row->product_id)
                                            <a href="{{ route('products.edit', $row->product_id) }}" class="hover:text-brand-700">{{ $row->name }}</a>
                                        @else
                                            {{ $row->name }}
                                        @endif
                                    </td>
                                    <td class="tabular text-right">{{ $row->units }}</td>
                                    <td class="text-right font-medium text-slate-900">{{ $row->revenue }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        {{-- Low Stock --}}
        <div x-show="tab === 'lowstock'" x-cloak>
            <x-card flush>
                @if ($lowStock->isEmpty())
                    <x-empty-state icon="box" title="Stock Levels Look Healthy"
                        description="A variant shows up here once it drops to {{ config('shop.low_stock_threshold', 5) }} or fewer in stock. Only variants with inventory tracking switched on are counted." />
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Product</th><th>SKU</th><th class="text-right">In Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lowStock as $variant)
                                <tr class="vx-rail {{ $variant->inventory_qty <= 0 ? 'vx-rail-danger' : 'vx-rail-warn' }}">
                                    <td class="font-medium text-slate-900">
                                        <a href="{{ route('products.edit', $variant->product_id) }}" class="hover:text-brand-700">{{ $variant->product?->name }}</a>
                                        @if ($variant->name !== 'Default')
                                            <span class="block text-xs text-slate-500">{{ $variant->name }}</span>
                                        @endif
                                    </td>
                                    <td class="font-mono text-xs text-slate-500">{{ $variant->sku ?: 'No SKU' }}</td>
                                    <td class="text-right">
                                        <x-badge :color="$variant->inventory_qty <= 0 ? 'danger' : 'warn'" dot>{{ $variant->inventory_qty }}</x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        {{-- New Customers --}}
        <div x-show="tab === 'customers'" x-cloak>
            <x-card flush>
                @if ($newCustomers->isEmpty())
                    <x-empty-state icon="users" title="No Customers Yet"
                        description="Anyone who checks out gets a customer record here, whether they registered an account or checked out as a guest." />
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Customer</th><th>Email</th><th class="text-right">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($newCustomers as $customer)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-brand-200">{{ $customer->initials }}</span>
                                            <a href="{{ route('customers.show', $customer) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $customer->name }}</a>
                                        </div>
                                    </td>
                                    <td class="text-slate-500">{{ $customer->email }}</td>
                                    <td class="text-right text-slate-500">{{ $customer->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>
    </section>

    <div class="section-divider my-8"></div>

    {{-- Catalog snapshot. Reference figures, so a compact hairline strip rather
         than four more cards competing with the revenue hero. --}}
    <section aria-labelledby="catalog-heading">
        <h2 id="catalog-heading" class="mb-3 text-sm font-semibold text-slate-900">Catalog</h2>
        <dl class="grid grid-cols-2 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 sm:grid-cols-4">
            @foreach ([
                ['Total Products', $catalogCounts['products'], 'bag'],
                ['Active', $catalogCounts['active'], 'check-circle'],
                ['Draft', $catalogCounts['draft'], 'edit'],
                ['Customers', $catalogCounts['customers'], 'users'],
            ] as $i => [$label, $value, $icon])
                <div @class([
                    'flex items-center gap-3 px-5 py-4',
                    'border-b border-slate-200 sm:border-b-0' => $i < 2,
                    'border-r border-slate-200 sm:border-r' => $i % 2 === 0,
                    'sm:border-r sm:border-slate-200' => $i === 1,
                ])>
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-500 ring-1 ring-slate-200">
                        <x-icon :name="$icon" class="h-4 w-4" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <dt class="vx-eyebrow truncate">{{ $label }}</dt>
                        <dd class="tabular mt-1 text-lg font-semibold text-slate-900">{{ $value }}</dd>
                    </div>
                </div>
            @endforeach
        </dl>
    </section>
</x-layouts.app>
