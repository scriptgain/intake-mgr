<x-layouts.shop :title="$article->title">
    @include('shop.help._prose-style')

    <section class="border-b border-shop-line bg-slate-50">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-6">
            <nav class="flex flex-wrap items-center gap-2 text-sm text-shop-muted" aria-label="Breadcrumb">
                <a href="{{ route('shop.help') }}" class="inline-flex items-center gap-1.5 hover:text-brand-700">
                    <x-icon name="book" class="h-4 w-4" /> Help Center
                </a>
                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300" />
                <a href="{{ route('shop.help.category', $category) }}" class="hover:text-brand-700">{{ $category->name }}</a>
                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300" />
                <span class="font-medium text-shop-ink" aria-current="page">{{ $article->title }}</span>
            </nav>
        </div>
    </section>

    <div class="{{ $maxWidth }} mx-auto grid grid-cols-1 gap-10 px-4 sm:px-6 lg:px-8 py-10 lg:grid-cols-12">
        <article class="min-w-0 lg:col-span-8">
            <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-shop-ink">{{ $article->title }}</h1>
            @if ($article->excerpt)
                <p class="mt-2 text-lg text-shop-muted">{{ $article->excerpt }}</p>
            @endif

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shop-prose shadow-sm">
                {!! $article->body_html !!}
            </div>

            <div class="mt-10 border-t border-shop-line pt-6">
                <a href="{{ route('shop.help.category', $category) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800">
                    <x-icon name="chevron-left" class="h-4 w-4" /> Back To {{ $category->name }}
                </a>
            </div>
        </article>

        <aside class="min-w-0 lg:col-span-4">
            <div class="rounded-2xl bg-white p-5 ring-1 ring-shop-line">
                <p class="text-sm font-semibold text-shop-ink">More In {{ $category->name }}</p>
                @if ($related->isEmpty())
                    <p class="mt-3 text-sm text-shop-muted">No other articles in this topic yet.</p>
                @else
                    <ul class="mt-3 space-y-2">
                        @foreach ($related as $other)
                            <li>
                                <a href="{{ route('shop.help.article', [$category, $other]) }}" class="group inline-flex items-start gap-2 text-sm text-shop-ink/80 hover:text-brand-700">
                                    <x-icon name="chevron-right" class="mt-0.5 h-4 w-4 shrink-0 text-shop-muted group-hover:text-brand-600" />
                                    <span class="min-w-0">{{ $other->title }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if (config('shop.store_email'))
                <div class="mt-5 rounded-2xl bg-brand-50 p-5 ring-1 ring-inset ring-brand-200">
                    <p class="text-sm font-semibold text-shop-ink">Still Need Help?</p>
                    <p class="mt-1 text-sm text-shop-muted">Our team is happy to answer any question.</p>
                    <a href="mailto:{{ config('shop.store_email') }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800">
                        <x-icon name="envelope" class="h-4 w-4" /> {{ config('shop.store_email') }}
                    </a>
                </div>
            @endif
        </aside>
    </div>

</x-layouts.shop>
