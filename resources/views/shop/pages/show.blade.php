<x-layouts.shop :title="$page->title">
    @include('shop.help._prose-style')

    <section class="border-b border-shop-line bg-slate-50">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8">
            <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">{{ $page->title }}</h1>
            <p class="mt-2 text-sm text-shop-muted">Last Updated {{ $page->updated_at?->format(config('shop.date_format', 'M j, Y')) }}</p>
        </div>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="shop-prose max-w-none">
            {!! $page->body_html !!}
        </div>

        @if (config('shop.store_email'))
            <div class="mt-10 rounded-2xl bg-slate-50 p-5 ring-1 ring-shop-line">
                <p class="text-sm text-shop-muted">Questions about this policy? Email us at
                    <a href="mailto:{{ config('shop.store_email') }}" class="font-medium text-brand-700 hover:text-brand-800">{{ config('shop.store_email') }}</a>.
                </p>
            </div>
        @endif
    </section>

</x-layouts.shop>
