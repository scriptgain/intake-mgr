<x-layouts.shop title="My Account">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">Hi, {{ $customer->first_name }}</h1>
        <p class="mt-2 text-shop-muted">Track your requests, tickets, work orders, and invoices.</p>
    </section>

    <x-account-tabs />

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Quick links into the service-desk sections. --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-12">
            @foreach ([
                ['Requests', 'shop.account.requests', 'folder'],
                ['Tickets', 'shop.account.tickets', 'envelope'],
                ['Work Orders', 'shop.account.work-orders', 'clock'],
                ['Invoices', 'shop.account.invoices', 'credit-card'],
            ] as [$label, $routeName, $icon])
                <a href="{{ route($routeName) }}" class="group flex items-center gap-3 rounded-xl bg-white ring-1 ring-inset ring-shop-line p-4 transition hover:ring-brand-200 hover:bg-brand-50/30">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                        <x-icon :name="$icon" class="w-5 h-5" />
                    </span>
                    <span class="text-sm font-semibold text-shop-ink transition group-hover:text-brand-700">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <div class="flex items-center justify-between gap-4 mb-6">
            <h2 class="text-lg font-semibold text-shop-ink">Recent Invoices</h2>
            <x-button href="{{ route('shop.request') }}" icon="plus" size="sm">Request Service</x-button>
        </div>
        @if ($orders->isEmpty())
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white">
                <x-empty-state icon="credit-card" title="No Invoices Yet" description="When we bill you for completed work, your invoices will show up here.">
                    <x-slot:action>
                        <x-button href="{{ route('shop.request') }}" icon="plus">Request Service</x-button>
                    </x-slot:action>
                </x-empty-state>
            </div>
        @else
            {{-- Padded container with rounded rows: each hover ring is fully
                 rounded and inset, so no square corners at the container edge
                 and no doubled border against a divider. --}}
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white p-1.5">
                @foreach ($orders as $order)
                    <a href="{{ route('shop.account.order', $order) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-lg px-4 py-4 transition hover:bg-slate-50 hover:ring-1 hover:ring-inset hover:ring-slate-200">
                        <div class="min-w-0">
                            <p class="font-medium text-shop-ink">{{ $order->number }}</p>
                            <p class="text-sm text-shop-muted">{{ $order->created_at->format('F j, Y') }}</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <x-badge :color="$order->financial_badge" dot>{{ \Illuminate\Support\Str::headline($order->financial_status) }}</x-badge>
                            <span class="font-semibold text-shop-ink tabular w-20 text-right">{{ $order->total_formatted }}</span>
                            <x-icon name="chevron-right" class="w-4 h-4 text-shop-muted" />
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($defaultAddress)
            <div class="mt-12 pt-8 border-t border-shop-line">
                <h2 class="text-lg font-semibold text-shop-ink mb-3">Default Address</h2>
                <p class="text-sm text-shop-muted leading-relaxed">{{ $defaultAddress->summary }}</p>
                <a href="{{ route('shop.account.addresses') }}" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800 transition">
                    Manage Addresses <x-icon name="chevron-right" class="w-4 h-4" />
                </a>
            </div>
        @endif
    </section>

</x-layouts.shop>
