<x-layouts.shop title="My Invoices">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">My Account</h1>
        <p class="mt-2 text-shop-muted">Your invoices and payment history.</p>
    </section>

    <x-account-tabs />

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <h2 class="text-lg font-semibold text-shop-ink mb-6">Invoices</h2>

        @if ($invoices->isEmpty())
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white">
                <x-empty-state icon="credit-card" title="No Invoices Yet" description="When we bill you for completed work, your invoices will appear here and you can pay any that are outstanding." />
            </div>
        @else
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white p-1.5">
                @foreach ($invoices as $invoice)
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg px-4 py-4 transition hover:bg-slate-50 hover:ring-1 hover:ring-inset hover:ring-slate-200">
                        <a href="{{ route('shop.account.order', $invoice) }}" class="min-w-0 flex-1">
                            <p class="font-medium text-shop-ink">{{ $invoice->number }}</p>
                            <p class="text-sm text-shop-muted">{{ $invoice->created_at->format('F j, Y') }}</p>
                        </a>
                        <div class="flex items-center gap-3 shrink-0">
                            <x-badge :color="$invoice->financial_badge" dot>{{ \Illuminate\Support\Str::headline($invoice->financial_status) }}</x-badge>
                            <span class="font-semibold text-shop-ink tabular w-20 text-right">{{ $invoice->total_formatted }}</span>
                            @if ($invoice->pay_url)
                                <x-button href="{{ $invoice->pay_url }}" size="sm">Pay</x-button>
                            @else
                                <a href="{{ route('shop.account.order', $invoice) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800 transition">
                                    View <x-icon name="chevron-right" class="w-4 h-4" />
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">{{ $invoices->links() }}</div>
        @endif
    </section>

</x-layouts.shop>
