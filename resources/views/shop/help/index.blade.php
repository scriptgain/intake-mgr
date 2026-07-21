<x-layouts.shop title="Help Center">

    {{-- Hero + search --}}
    <section class="border-b border-shop-line bg-slate-50">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-10">
            <p class="vx-eyebrow mb-2 text-brand-600">Help Center</p>
            <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">How Can We Help?</h1>
            <p class="mt-2 max-w-2xl text-shop-muted">Browse the topics below or search for an answer.</p>

            <form action="{{ route('shop.help.search') }}" method="GET" class="mt-6 max-w-xl">
                <label class="relative block">
                    <span class="sr-only">Search The Help Center</span>
                    <x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-shop-muted" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search For An Answer"
                        class="w-full rounded-full border border-slate-200 bg-white py-3 pl-12 pr-4 text-sm text-shop-ink placeholder:text-shop-muted focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 transition">
                </label>
            </form>
        </div>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if ($categories->isEmpty())
            <x-empty-state icon="book" title="No Help Topics Yet"
                description="The store's help articles will appear here soon. In the meantime, please reach out to us directly." />
        @else
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $category)
                    <div class="flex min-w-0 flex-col rounded-2xl bg-white p-6 ring-1 ring-shop-line transition hover:ring-brand-200 hover:shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                                <x-icon :name="$category->icon ?: 'book'" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('shop.help.category', $category) }}" class="block truncate text-base font-semibold text-shop-ink hover:text-brand-700">{{ $category->name }}</a>
                                <p class="text-xs text-shop-muted">{{ $category->published_articles_count ?? $category->publishedArticles->count() }} {{ \Illuminate\Support\Str::plural('Article', $category->publishedArticles->count()) }}</p>
                            </div>
                        </div>

                        @if ($category->description)
                            <p class="mt-3 text-sm leading-relaxed text-shop-muted">{{ $category->description }}</p>
                        @endif

                        @if ($category->publishedArticles->isNotEmpty())
                            <ul class="mt-4 space-y-2 border-t border-shop-line pt-4">
                                @foreach ($category->publishedArticles->take(4) as $article)
                                    <li>
                                        <a href="{{ route('shop.help.article', [$category, $article]) }}" class="group inline-flex items-start gap-2 text-sm text-shop-ink/80 hover:text-brand-700">
                                            <x-icon name="chevron-right" class="mt-0.5 h-4 w-4 shrink-0 text-shop-muted group-hover:text-brand-600" />
                                            <span class="min-w-0">{{ $article->title }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <a href="{{ route('shop.help.category', $category) }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800">
                            View All <x-icon name="chevron-right" class="h-4 w-4" />
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

</x-layouts.shop>
