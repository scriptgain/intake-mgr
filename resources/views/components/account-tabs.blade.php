@props(['maxWidth' => null])
@php
    $maxWidth = $maxWidth ?? config('shop.max_width', 'max-w-6xl');

    // [label, route name, icon, active-match patterns]. A detail page keeps its
    // list tab lit (routeIs does not treat "requests" as a parent of "request").
    $tabs = [
        ['Overview', 'shop.account', 'dashboard', ['shop.account']],
        ['Requests', 'shop.account.requests', 'folder', ['shop.account.requests', 'shop.account.request']],
        ['Tickets', 'shop.account.tickets', 'envelope', ['shop.account.tickets', 'shop.account.ticket']],
        ['Quotes', 'shop.account.quotes', 'document', ['shop.account.quotes', 'shop.account.quote']],
        ['Work Orders', 'shop.account.work-orders', 'clock', ['shop.account.work-orders', 'shop.account.work-order']],
        ['Invoices', 'shop.account.invoices', 'credit-card', ['shop.account.invoices', 'shop.account.order']],
        ['Profile', 'shop.account.profile', 'user', ['shop.account.profile']],
        ['Addresses', 'shop.account.addresses', 'home', ['shop.account.addresses']],
    ];
@endphp

<section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
    {{-- Account tabs (house style: tabs over a long scroll). --}}
    <div class="flex items-center justify-between gap-4 border-b border-shop-line mb-8">
        <div class="flex items-center gap-1 overflow-x-auto no-scrollbar">
            @foreach ($tabs as [$label, $routeName, $icon, $patterns])
                @php $active = request()->routeIs($patterns); @endphp
                <a href="{{ route($routeName) }}" @if ($active) aria-current="page" @endif
                   class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 -mb-px shrink-0 transition {{ $active ? 'border-brand-600 text-brand-700' : 'border-transparent text-shop-muted hover:text-shop-ink' }}">
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
