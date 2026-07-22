<x-layouts.shop>
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden text-white"
             style="background:linear-gradient(135deg,#1a120d 0%,#2a1810 42%,#3f1508 78%,#7c2d12 130%);">
        {{-- Warm brand glows for depth. --}}
        <div class="pointer-events-none absolute -left-40 -top-24 -z-10 h-[30rem] w-[30rem] rounded-full bg-brand-500/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 top-40 -z-10 h-[26rem] w-[26rem] rounded-full bg-brand-600/20 blur-3xl"></div>
        {{-- Dot texture, faded out toward the bottom. --}}
        <div class="pointer-events-none absolute inset-0 -z-10"
             style="background-image:radial-gradient(circle at center, rgba(255,255,255,0.07) 1px, transparent 1.4px);background-size:22px 22px;-webkit-mask-image:linear-gradient(to bottom, black 10%, transparent 88%);mask-image:linear-gradient(to bottom, black 10%, transparent 88%);"></div>

        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-24 lg:py-28">
            <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-8">

                {{-- Left: the pitch --}}
                <div class="max-w-xl">
                    <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-200 ring-1 ring-inset ring-white/15 backdrop-blur">
                        <x-icon name="bolt" class="w-3.5 h-3.5 shrink-0 text-brand-300" />
                        {{ config('shop.store_tagline') }}
                    </p>

                    <h1 class="mt-6 font-display text-5xl sm:text-6xl lg:text-[4.25rem] font-semibold leading-[0.98] tracking-tight">
                        Service You Can<br class="hidden sm:block"> <span class="bg-gradient-to-r from-brand-300 to-brand-500 bg-clip-text text-transparent">Count On.</span>
                    </h1>

                    <p class="mt-6 max-w-lg text-lg leading-relaxed text-brand-100/80">
                        Tell us what you need and we take it from there: fast scheduling, clear updates, and work done right the first time.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-3">
                        <a href="{{ route('shop.request') }}"
                           class="group inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-semibold text-brand-800 shadow-lg shadow-brand-950/30 transition hover:bg-brand-50">
                            <x-icon name="plus" class="w-4 h-4 shrink-0" />
                            Request Service
                            <x-icon name="chevron-right" class="w-4 h-4 shrink-0 transition group-hover:translate-x-0.5" />
                        </a>
                        <a href="{{ route('shop.account.login') }}"
                           class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-6 py-3.5 text-sm font-semibold text-white ring-1 ring-inset ring-white/20 backdrop-blur transition hover:bg-white/15">
                            Customer Sign In
                        </a>
                    </div>

                    <div class="mt-10 flex flex-wrap gap-2.5">
                        @foreach ([['bolt','Fast Response'], ['shield','Vetted, Insured Pros'], ['check-circle','Track It All Online']] as [$icon, $label])
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/[0.07] px-3.5 py-2 text-sm font-medium text-brand-50 ring-1 ring-inset ring-white/10">
                                <x-icon :name="$icon" class="w-4 h-4 shrink-0 text-brand-300" />
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- Right: a live work-order card, so the hero shows the product,
                     not just decoration. --}}
                <div class="relative lg:justify-self-end">
                    <div class="pointer-events-none absolute -right-3 -top-3 h-full w-full rounded-3xl bg-white/[0.06] ring-1 ring-inset ring-white/10"></div>

                    <div class="relative w-full max-w-md rounded-3xl bg-white p-6 text-shop-ink shadow-2xl shadow-brand-950/40 ring-1 ring-black/5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.14em] text-shop-muted">
                                <x-icon name="truck" class="w-4 h-4 shrink-0 text-brand-600" />
                                Work Order WO-1042
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 ring-1 ring-inset ring-brand-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span> Scheduled
                            </span>
                        </div>

                        <div class="mt-5 flex items-center gap-3">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700 ring-1 ring-inset ring-brand-200">JM</span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-shop-ink">Jordan Miller</p>
                                <p class="text-xs text-shop-muted">Pool &amp; Spa Technician</p>
                            </div>
                            <div class="ml-auto text-right">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-shop-muted">Next Visit</p>
                                <p class="text-sm font-semibold text-shop-ink">Tue, 10:00 AM</p>
                            </div>
                        </div>

                        {{-- Mini status track: a baseline rail with a progress
                             fill behind evenly-spaced nodes, labels under each. --}}
                        @php $steps = [['Requested', 'done'], ['Scheduled', 'current'], ['In Progress', 'todo'], ['Done', 'todo']]; @endphp
                        <div class="relative mt-6 px-1">
                            <div class="absolute left-4 right-4 top-3 h-0.5 rounded-full bg-slate-200"></div>
                            <div class="absolute left-4 top-3 h-0.5 rounded-full bg-brand-500" style="width:calc(33.333% - 0.5rem);"></div>
                            <div class="relative flex justify-between">
                                @foreach ($steps as [$label, $state])
                                    <div class="flex w-16 flex-col items-center gap-2">
                                        @if ($state === 'done')
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-white ring-4 ring-brand-100">
                                                <x-icon name="check" class="h-3.5 w-3.5" />
                                            </span>
                                        @elseif ($state === 'current')
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white ring-2 ring-brand-500">
                                                <span class="h-2 w-2 rounded-full bg-brand-600"></span>
                                            </span>
                                        @else
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white ring-2 ring-slate-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                                            </span>
                                        @endif
                                        <span @class([
                                            'text-center text-[10px] font-semibold uppercase tracking-wide leading-tight',
                                            'text-brand-700' => $state === 'current',
                                            'text-shop-ink/70' => $state === 'done',
                                            'text-slate-400' => $state === 'todo',
                                        ])>{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-between border-t border-shop-line pt-4">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-shop-muted">Estimate</p>
                                <p class="text-lg font-semibold text-shop-ink tabular">$99.00</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">
                                <x-icon name="credit-card" class="w-4 h-4 shrink-0" /> Pay Online
                            </span>
                        </div>
                    </div>
                </div>

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

    {{-- What we help with. A curated, even set of service categories so the
         section always reads sensibly regardless of the catalog. --}}
    <div class="section-divider"></div>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
        <div class="flex items-end justify-between gap-4 mb-10">
            <div>
                <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-shop-ink">What We Help With</h2>
                <p class="mt-1 text-shop-muted">From quick fixes to scheduled maintenance, we have you covered.</p>
            </div>
            <a href="{{ route('shop.request') }}" class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800 transition shrink-0">
                Request Service <x-icon name="chevron-right" class="w-4 h-4" />
            </a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ([
                ['refresh', 'Plumbing', 'Leaks, clogs, water heaters, fixtures, and repiping.'],
                ['sync', 'Heating &amp; Cooling', 'HVAC tune-ups, repairs, and seasonal maintenance.'],
                ['bolt', 'Electrical', 'Outlets, panels, lighting, and safety inspections.'],
                ['cloud', 'Pool &amp; Spa', 'Cleaning, chemical balancing, and equipment service.'],
                ['settings', 'Appliance Repair', 'Diagnostics and repairs for major home appliances.'],
                ['home', 'Handyman &amp; Repairs', 'The odd jobs and fixes that keep a home running.'],
            ] as [$icon, $title, $body])
                <a href="{{ route('shop.request') }}" class="group flex items-start gap-3 rounded-2xl bg-white ring-1 ring-inset ring-shop-line p-5 transition hover:ring-brand-200 hover:bg-brand-50/30">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                        <x-icon :name="$icon" class="w-5 h-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-shop-ink transition group-hover:text-brand-700">{!! $title !!}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-shop-muted">{{ $body }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

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
