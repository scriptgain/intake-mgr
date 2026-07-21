<x-layouts.shop :title="$category->name">

    <section class="border-b border-shop-line bg-slate-50">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-8">
            <nav class="flex items-center gap-2 text-sm text-shop-muted" aria-label="Breadcrumb">
                <a href="{{ route('shop.help') }}" class="inline-flex items-center gap-1.5 hover:text-brand-700">
                    <x-icon name="book" class="h-4 w-4" /> Help Center
                </a>
                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300" />
                <span class="font-medium text-shop-ink" aria-current="page">{{ $category->name }}</span>
            </nav>

            <div class="mt-4 flex items-start gap-4">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                    <x-icon :name="$category->icon ?: 'book'" class="h-6 w-6" />
                </span>
                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-shop-ink">{{ $category->name }}</h1>
                    @if ($category->description)
                        <p class="mt-1.5 max-w-2xl text-shop-muted">{{ $category->description }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @if ($category->publishedArticles->isEmpty())
            <x-empty-state icon="book" title="No Articles Yet"
                description="There are no published articles in this topic right now. Please check back soon.">
                <x-slot:action>
                    <a href="{{ route('shop.help') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800">
                        <x-icon name="chevron-left" class="h-4 w-4" /> Back To Help Center
                    </a>
                </x-slot:action>
            </x-empty-state>
        @else
            <ul class="divide-y divide-shop-line overflow-hidden rounded-2xl bg-white ring-1 ring-shop-line">
                @foreach ($category->publishedArticles as $article)
                    <li>
                        <a href="{{ route('shop.help.article', [$category, $article]) }}" class="group flex items-start gap-4 px-5 py-4 transition hover:bg-slate-50 sm:px-6">
                            <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-shop-muted ring-1 ring-inset ring-slate-200 group-hover:bg-brand-50 group-hover:text-brand-600 group-hover:ring-brand-200">
                                <x-icon name="book" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-shop-ink group-hover:text-brand-700">{{ $article->title }}</span>
                                @if ($article->excerpt)
                                    <span class="mt-0.5 block text-sm text-shop-muted">{{ $article->excerpt }}</span>
                                @endif
                            </span>
                            <x-icon name="chevron-right" class="mt-1 h-5 w-5 shrink-0 text-slate-300 group-hover:text-brand-500" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

</x-layouts.shop>
