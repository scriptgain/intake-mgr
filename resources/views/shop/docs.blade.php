<x-layouts.shop title="API Reference">
    @php
        $verbClass = [
            'GET' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'POST' => 'bg-brand-50 text-brand-700 ring-brand-200',
            'PUT' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'DELETE' => 'bg-rose-50 text-rose-700 ring-rose-200',
        ];
    @endphp

    {{-- Header --}}
    <section class="relative isolate overflow-hidden text-white" style="background:linear-gradient(135deg,#1a120d 0%,#2a1810 55%,#3f1508 120%);">
        <div class="pointer-events-none absolute -right-24 -top-24 -z-10 h-96 w-96 rounded-full bg-brand-500/20 blur-3xl"></div>
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
            <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-200 ring-1 ring-inset ring-white/15">
                <x-icon name="server" class="w-3.5 h-3.5 shrink-0" /> Developer Reference
            </p>
            <h1 class="mt-5 font-display text-4xl sm:text-5xl font-semibold tracking-tight">{{ $meta['title'] }}</h1>
            <p class="mt-4 max-w-2xl text-lg leading-relaxed text-brand-100/80">A bearer-token REST API over the whole service desk. Everything you can do in the panel, you can automate here.</p>
            <div class="mt-7 flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-4 py-2.5 font-mono text-sm ring-1 ring-inset ring-white/15">{{ $meta['base_url'] }}</span>
                <a href="{{ route('shop.docs.openapi') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-brand-800 transition hover:bg-brand-50">
                    <x-icon name="download" class="w-4 h-4 shrink-0" /> OpenAPI Spec
                </a>
            </div>
        </div>
    </section>

    <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-14">
        <div class="grid gap-12 lg:grid-cols-[220px_1fr]">

            {{-- Resource nav --}}
            <nav class="lg:sticky lg:top-24 self-start hidden lg:block">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-shop-muted">Reference</p>
                <ul class="mt-3 space-y-1 text-sm">
                    <li><a href="#auth" class="block rounded-lg px-3 py-1.5 text-shop-muted hover:bg-brand-50/50 hover:text-brand-700">Authentication</a></li>
                    <li><a href="#conventions" class="block rounded-lg px-3 py-1.5 text-shop-muted hover:bg-brand-50/50 hover:text-brand-700">Conventions</a></li>
                    @foreach ($groups as $g)
                        <li><a href="#{{ $g['key'] }}" class="block rounded-lg px-3 py-1.5 text-shop-muted hover:bg-brand-50/50 hover:text-brand-700">{{ $g['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <main class="min-w-0 max-w-3xl">
                {{-- Authentication --}}
                <section id="auth" class="scroll-mt-24">
                    <h2 class="text-2xl font-semibold tracking-tight text-shop-ink">Authentication</h2>
                    <p class="mt-3 text-shop-muted">Every request needs a bearer token in the <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-sm text-brand-700">Authorization</code> header. A store owner mints one in the admin panel under <b>Settings &rarr; API Tokens</b>. Tokens start with <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-sm text-brand-700">vlt_</code> and are shown once.</p>
                    <pre class="mt-4 overflow-x-auto rounded-xl bg-shop-ink p-4 text-sm text-slate-100"><code class="font-mono">curl {{ $meta['base_url'] }}/tickets \
  -H "Authorization: Bearer vlt_your_token_here" \
  -H "Accept: application/json"</code></pre>
                </section>

                {{-- Conventions --}}
                <section id="conventions" class="mt-14 scroll-mt-24">
                    <h2 class="text-2xl font-semibold tracking-tight text-shop-ink">Conventions</h2>
                    <dl class="mt-4 divide-y divide-shop-line overflow-hidden rounded-xl ring-1 ring-inset ring-shop-line">
                        @foreach ([
                            ['Base URL', $meta['base_url']],
                            ['Format', 'JSON request + response bodies. Send Accept: application/json.'],
                            ['Pagination', 'List endpoints return a paginator. Use ?per_page='.$meta['per_page'].' (max '.$meta['max_per_page'].') and ?page=N.'],
                            ['Filtering', 'Most lists accept ?q= for search and resource-specific filters (see each endpoint).'],
                            ['Rate limit', $meta['rate_limit'].' requests per minute per token. Exceeding it returns 429 with X-RateLimit headers.'],
                            ['Errors', '401 unauthenticated, 403 forbidden, 404 not found, 422 validation (with an errors object), 429 rate limited.'],
                            ['Money', 'Amounts are read as integer cents plus a formatted string. Totals are never writable over the API.'],
                        ] as [$k, $v])
                            <div class="flex flex-col gap-1 bg-white px-4 py-3 sm:flex-row sm:gap-4">
                                <dt class="w-32 shrink-0 text-sm font-semibold text-shop-ink">{{ $k }}</dt>
                                <dd class="text-sm text-shop-muted">{{ $v }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                {{-- Resources --}}
                @foreach ($groups as $g)
                    <section id="{{ $g['key'] }}" class="mt-14 scroll-mt-24">
                        <h2 class="text-2xl font-semibold tracking-tight text-shop-ink">{{ $g['label'] }}</h2>
                        <p class="mt-2 text-shop-muted">{{ $g['desc'] }}</p>

                        <div class="mt-5 overflow-hidden rounded-xl ring-1 ring-inset ring-shop-line divide-y divide-shop-line">
                            @foreach ($g['endpoints'] as $ep)
                                @php
                                    [$method, $path, $summary] = [$ep[0], $ep[1], $ep[2]];
                                    $params = $ep[3] ?? [];
                                    $body = $ep[4] ?? null;
                                @endphp
                                <div class="bg-white px-4 py-3.5">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="inline-flex w-16 justify-center rounded-md px-2 py-1 text-[11px] font-bold ring-1 ring-inset {{ $verbClass[$method] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $method }}</span>
                                        <code class="font-mono text-sm text-shop-ink">{{ $path }}</code>
                                    </div>
                                    <p class="mt-2 text-sm text-shop-muted">{{ $summary }}</p>
                                    @if ($params)
                                        <p class="mt-2 text-xs text-shop-muted"><span class="font-semibold text-shop-ink">Query:</span> {{ collect($params)->pluck('name')->implode(', ') }}</p>
                                    @endif
                                    @if ($body)
                                        <p class="mt-1 text-xs text-shop-muted"><span class="font-semibold text-shop-ink">Body:</span> <span class="font-mono">{{ implode(', ', $body) }}</span></p>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <details class="mt-3 group">
                            <summary class="cursor-pointer text-sm font-medium text-brand-700 hover:text-brand-800">Response fields</summary>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($g['fields'] as $field)
                                    <span class="rounded-md bg-slate-50 px-2 py-1 font-mono text-xs text-slate-600 ring-1 ring-inset ring-shop-line">{{ $field }}</span>
                                @endforeach
                            </div>
                        </details>
                    </section>
                @endforeach

                <p class="mt-16 border-t border-shop-line pt-6 text-sm text-shop-muted">Machine-readable schema: <a href="{{ route('shop.docs.openapi') }}" class="font-medium text-brand-700 hover:text-brand-800">openapi.json</a>. Import it into Postman or an OpenAPI client to generate a request collection.</p>
            </main>
        </div>
    </div>
</x-layouts.shop>
