<x-layouts.shop title="My Account">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">Hi, {{ $customer->first_name }}</h1>
        <p class="mt-2 text-shop-muted">Manage your orders, profile, and saved addresses.</p>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Account tabs (house style: tabs over a long scroll). --}}
        <div class="flex items-center justify-between gap-4 border-b border-shop-line mb-8">
            <div class="flex items-center gap-1 overflow-x-auto no-scrollbar">
                @foreach ([['Orders', 'shop.account', 'bag'], ['Profile', 'shop.account.profile', 'user'], ['Addresses', 'shop.account.addresses', 'home']] as [$label, $routeName, $icon])
                    @php $active = request()->routeIs($routeName); @endphp
                    <a href="{{ route($routeName) }}" class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 -mb-px shrink-0 transition {{ $active ? 'border-brand-600 text-brand-700' : 'border-transparent text-shop-muted hover:text-shop-ink' }}">
                        <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
                    </a>
                @endforeach
            </div>
            <form method="POST" action="{{ route('shop.account.logout') }}" class="shrink-0 mb-2">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-medium text-shop-muted hover:text-rose-600 transition">
                    <x-icon name="x-circle" class="w-4 h-4" /> Sign Out
                </button>
            </form>
        </div>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        @if ($orders->isEmpty())
            <x-empty-state icon="bag" title="No Orders Yet" description="When you place an order, it will show up here.">
                <x-slot:action>
                    <x-button href="{{ route('shop.catalog') }}">Start Shopping</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            {{-- Padded container with rounded rows: each hover ring is fully
                 rounded and inset, so no square corners at the container edge
                 and no doubled border against a divider. --}}
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white p-1.5">
                @foreach ($orders as $order)
                    <a href="{{ route('shop.account.order', $order) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-lg px-4 py-4 transition hover:bg-slate-50 hover:ring-1 hover:ring-inset hover:ring-slate-200">
                        <div class="min-w-0">
                            <p class="font-medium text-shop-ink">{{ $order->number }}</p>
                            <p class="text-sm text-shop-muted">{{ $order->created_at->format('F j, Y') }} &middot; {{ $order->item_count }} {{ \Illuminate\Support\Str::plural('Item', $order->item_count) }}</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <x-badge :color="$order->financial_badge" dot>{{ \Illuminate\Support\Str::headline($order->financial_status) }}</x-badge>
                            <x-badge :color="$order->fulfillment_badge" dot>{{ \Illuminate\Support\Str::headline($order->fulfillment_status) }}</x-badge>
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
