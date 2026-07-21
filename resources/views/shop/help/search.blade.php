<x-layouts.shop title="Search Help">

    <section class="border-b border-shop-line bg-slate-50">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-8">
            <nav class="flex items-center gap-2 text-sm text-shop-muted" aria-label="Breadcrumb">
                <a href="{{ route('shop.help') }}" class="inline-flex items-center gap-1.5 hover:text-brand-700">
                    <x-icon name="book" class="h-4 w-4" /> Help Center
                </a>
                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300" />
                <span class="font-medium text-shop-ink" aria-current="page">Search</span>
            </nav>

            <h1 class="mt-4 text-2xl sm:text-3xl font-semibold tracking-tight text-shop-ink">Search The Help Center</h1>

            <form action="{{ route('shop.help.search') }}" method="GET" class="mt-5 max-w-xl">
                <label class="relative block">
                    <span class="sr-only">Search The Help Center</span>
                    <x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-shop-muted" />
                    <input type="search" name="q" value="{{ $term }}" placeholder="Search For An Answer" autofocus
                        class="w-full rounded-full border border-slate-200 bg-white py-3 pl-12 pr-4 text-sm text-shop-ink placeholder:text-shop-muted focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 transition">
                </label>
            </form>
        </div>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @if ($term === '')
            <x-empty-state icon="search" title="Type Something To Search"
                description="Enter a word or phrase above and we'll look through every help article." />
        @elseif ($results->isEmpty())
            <x-empty-state icon="search" title="No Results Found"
                description="We couldn't find any articles matching &ldquo;{{ $term }}&rdquo;. Try a different or shorter term, or browse the topics instead.">
                <x-slot:action>
                    <a href="{{ route('shop.help') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800">
                        <x-icon name="chevron-left" class="h-4 w-4" /> Browse All Topics
                    </a>
                </x-slot:action>
            </x-empty-state>
        @else
            <p class="mb-4 text-sm text-shop-muted">{{ $results->count() }} {{ \Illuminate\Support\Str::plural('Result', $results->count()) }} For &ldquo;{{ $term }}&rdquo;</p>
            <ul class="divide-y divide-shop-line overflow-hidden rounded-2xl bg-white ring-1 ring-shop-line">
                @foreach ($results as $article)
                    <li>
                        <a href="{{ route('shop.help.article', [$article->category, $article]) }}" class="group block px-5 py-4 transition hover:bg-slate-50 sm:px-6">
                            <div class="flex items-center gap-2 text-xs text-shop-muted">
                                <x-icon :name="$article->category->icon ?: 'book'" class="h-3.5 w-3.5" /> {{ $article->category->name }}
                            </div>
                            <p class="mt-1 font-medium text-shop-ink group-hover:text-brand-700">{{ $article->title }}</p>
                            @if ($article->excerpt)
                                <p class="mt-0.5 text-sm text-shop-muted">{{ $article->excerpt }}</p>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

</x-layouts.shop>
