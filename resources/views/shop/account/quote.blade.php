<x-layouts.shop :title="'Quote ' . $quote->number">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">My Account</h1>
        <p class="mt-2 text-shop-muted">Review your estimate and let us know.</p>
    </section>

    <x-account-tabs />

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <a href="{{ route('shop.account.quotes') }}" class="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-shop-muted hover:text-shop-ink transition">
            <x-icon name="chevron-left" class="w-4 h-4" /> Back To Quotes
        </a>

        <div class="max-w-2xl">
            @include('shop._quote-body', [
                'acceptAction' => route('shop.account.quote.accept', $quote),
                'declineAction' => route('shop.account.quote.decline', $quote),
                'payUrl' => $payUrl,
            ])
        </div>
    </section>

</x-layouts.shop>
