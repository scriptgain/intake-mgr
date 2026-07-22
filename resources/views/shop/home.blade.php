<x-layouts.shop>
    @php
        // The home controller still passes catalog data; we only use products as
        // an optional "services we offer" teaser. Guard everything so an empty
        // catalog never breaks the landing page.
        $services = ($featured ?? collect())->isNotEmpty() ? $featured : ($newest ?? collect());
    @endphp

    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-white">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-br from-brand-50 via-white to-white"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 -z-10 h-96 w-96 rounded-full bg-brand-100/50 blur-3xl"></div>

        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24">
            <div class="max-w-2xl">
                <p class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-700 ring-1 ring-inset ring-brand-200">
                    {{ config('shop.store_tagline') }}
                </p>

                <h1 class="mt-6 font-display text-4xl sm:text-5xl lg:text-6xl font-semibold leading-[1.03] tracking-tight text-shop-ink">
                    Service You Can<br class="hidden sm:block"> Count On.
                </h1>

                <p class="mt-6 max-w-xl text-lg leading-relaxed text-shop-muted">
                    Tell us what you need and we will take it from there: fast scheduling, clear updates, and work done right the first time.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <x-button href="{{ route('shop.request') }}" size="lg" icon="plus">Request Service</x-button>
                    <a href="{{ route('shop.account.login') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-shop-ink ring-1 ring-inset ring-shop-line transition hover:bg-slate-50">
                        Customer Sign In
                        <x-icon name="chevron-right" class="w-4 h-4 shrink-0 text-slate-400" />
                    </a>
                </div>

                <dl class="mt-10 flex flex-wrap gap-x-8 gap-y-4">
                    <div class="flex items-start gap-2.5">
                        <x-icon name="bolt" class="mt-0.5 w-4 h-4 shrink-0 text-brand-600" />
                        <div>
                            <dt class="text-sm font-semibold text-shop-ink whitespace-nowrap">Fast Response</dt>
                            <dd class="text-xs text-shop-muted whitespace-nowrap">We reply quickly</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <x-icon name="shield" class="mt-0.5 w-4 h-4 shrink-0 text-brand-600" />
                        <div>
                            <dt class="text-sm font-semibold text-shop-ink whitespace-nowrap">Trusted Pros</dt>
                            <dd class="text-xs text-shop-muted whitespace-nowrap">Vetted, insured technicians</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <x-icon name="check-circle" class="mt-0.5 w-4 h-4 shrink-0 text-brand-600" />
                        <div>
                            <dt class="text-sm font-semibold text-shop-ink whitespace-nowrap">Track Everything</dt>
                            <dd class="text-xs text-shop-muted whitespace-nowrap">Updates, invoices, online</dd>
                        </div>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    <div class="section-divider"></div>

    {{-- How It Works --}}
    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
        <div class="max-w-2xl mb-10">
            <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-shop-ink">How It Works</h2>
            <p class="mt-1 text-shop-muted">Three simple steps from problem to solved.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            @foreach ([
                ['plus', 'Request', 'Submit a request in a couple of minutes. Add photos so we can see the problem.'],
                ['clock', 'We Schedule', 'We review your request, confirm the details, and book a technician at a time that works.'],
                ['credit-card', 'Track &amp; Pay', 'Follow progress, message our team, and pay your invoice online when the work is done.'],
            ] as $i => [$icon, $title, $body])
                <div class="relative rounded-2xl bg-white ring-1 ring-inset ring-shop-line p-6">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                        <x-icon :name="$icon" class="w-5 h-5" />
                    </span>
                    <p class="mt-4 text-xs font-semibold uppercase tracking-[0.14em] text-brand-700">Step {{ $i + 1 }}</p>
                    <h3 class="mt-1 text-lg font-semibold text-shop-ink">{!! $title !!}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-shop-muted">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Services teaser (only if the catalog has services) --}}
    @if ($services->count())
        <div class="section-divider"></div>

        <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
            <div class="flex items-end justify-between gap-4 mb-10">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-shop-ink">What We Help With</h2>
                    <p class="mt-1 text-shop-muted">A few of the services we offer.</p>
                </div>
                <a href="{{ route('shop.request') }}" class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800 transition shrink-0">
                    Request Service <x-icon name="chevron-right" class="w-4 h-4" />
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($services->take(6) as $service)
                    <a href="{{ route('shop.request', ['service_id' => $service->id]) }}" class="group flex items-start gap-3 rounded-2xl bg-white ring-1 ring-inset ring-shop-line p-5 transition hover:ring-brand-200 hover:bg-brand-50/30">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                            <x-icon name="check" class="w-5 h-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-shop-ink transition group-hover:text-brand-700">{{ $service->name }}</h3>
                            @if ($service->excerpt)
                                <p class="mt-1 line-clamp-2 text-sm text-shop-muted">{{ $service->excerpt }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Trust / CTA band --}}
    <section class="bg-chrome">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-16">
            <div class="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-center">
                <div class="max-w-xl">
                    <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-white">Need Something Fixed?</h2>
                    <p class="mt-2 text-slate-300">Send us a request now. It only takes a minute, and there is no obligation.</p>
                </div>
                <x-button href="{{ route('shop.request') }}" size="lg" icon="plus" class="shrink-0">Request Service</x-button>
            </div>
        </div>
    </section>

</x-layouts.shop>
