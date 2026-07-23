<x-layouts.shop :title="'Quote ' . $quote->number">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mx-auto max-w-2xl">
            <div class="mb-8 text-center">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                    <x-icon name="document" class="h-6 w-6" />
                </span>
                <h1 class="mt-4 text-2xl font-semibold tracking-tight text-shop-ink">Your Quote From {{ config('shop.store_name') }}</h1>
                <p class="mt-2 text-sm text-shop-muted">Review the estimate below and accept or decline. No account needed.</p>
            </div>

            @include('shop._quote-body', [
                'acceptAction' => route('shop.quote.public.accept', $quote->accept_token),
                'declineAction' => route('shop.quote.public.decline', $quote->accept_token),
                'payUrl' => null,
            ])

            <p class="mt-6 text-center text-xs text-shop-muted">
                Questions? Reply to the email that sent you this quote and we will help.
            </p>
        </div>
    </section>

</x-layouts.shop>
