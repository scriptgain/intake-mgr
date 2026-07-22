<x-layouts.shop title="My Work Orders">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">My Account</h1>
        <p class="mt-2 text-shop-muted">Scheduled and completed service visits.</p>
    </section>

    <x-account-tabs />

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <h2 class="text-lg font-semibold text-shop-ink mb-6">Work Orders</h2>

        @if ($workOrders->isEmpty())
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white">
                <x-empty-state icon="clock" title="No Work Orders Yet" description="When we schedule a visit for you, it will show up here with its date, status, and details." />
            </div>
        @else
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white p-1.5">
                @foreach ($workOrders as $wo)
                    <a href="{{ route('shop.account.work-order', $wo) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-lg px-4 py-4 transition hover:bg-slate-50 hover:ring-1 hover:ring-inset hover:ring-slate-200">
                        <div class="min-w-0">
                            <p class="font-medium text-shop-ink truncate">{{ $wo->title }}</p>
                            <p class="text-sm text-shop-muted">
                                {{ $wo->number }}
                                @if ($wo->scheduled_at)
                                    &middot; {{ $wo->scheduled_at->format('F j, Y g:i A') }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <x-badge :color="$wo->status_badge" dot>{{ $wo->status_label }}</x-badge>
                            @if ($wo->subtotal_cents > 0)
                                <span class="font-semibold text-shop-ink tabular w-20 text-right">{{ $wo->subtotal_formatted }}</span>
                            @endif
                            <x-icon name="chevron-right" class="w-4 h-4 text-shop-muted" />
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">{{ $workOrders->links() }}</div>
        @endif
    </section>

</x-layouts.shop>
