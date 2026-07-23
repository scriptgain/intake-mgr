<x-layouts.shop title="My Quotes">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">My Account</h1>
        <p class="mt-2 text-shop-muted">Estimates we have sent you to review.</p>
    </section>

    <x-account-tabs />

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <h2 class="text-lg font-semibold text-shop-ink mb-6">Quotes</h2>

        @if ($quotes->isEmpty())
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white">
                <x-empty-state icon="document" title="No Quotes Yet" description="When we send you an estimate for work, it will show up here for you to review, accept, or decline." />
            </div>
        @else
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white p-1.5">
                @foreach ($quotes as $quote)
                    <a href="{{ route('shop.account.quote', $quote) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-lg px-4 py-4 transition hover:bg-slate-50 hover:ring-1 hover:ring-inset hover:ring-slate-200">
                        <div class="min-w-0">
                            <p class="font-medium text-shop-ink truncate">{{ $quote->title }}</p>
                            <p class="text-sm text-shop-muted">
                                {{ $quote->number }}
                                @if ($quote->valid_until)
                                    &middot; Valid Until {{ $quote->valid_until->format('F j, Y') }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <x-badge :color="$quote->status_badge" dot>{{ $quote->status_label }}</x-badge>
                            <span class="font-semibold text-shop-ink tabular w-24 text-right">{{ $quote->total_formatted }}</span>
                            <x-icon name="chevron-right" class="w-4 h-4 text-shop-muted" />
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">{{ $quotes->links() }}</div>
        @endif
    </section>

</x-layouts.shop>
