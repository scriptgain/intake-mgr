<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' · ' . config('brand.name') : config('brand.name') }}</title>
    <x-favicon-links />

    {{-- The @font-face rules live in app.css; this just gets the file in flight
         early, since the face is used by essentially every element on the page. --}}
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/instrument-sans-latin.woff2') }}" crossorigin>

    {{-- Must precede the Alpine CDN in x-tailwind-cdn. Deferred scripts run in
         document order, so Alpine loaded first would fire alpine:init before
         this file could register dashboardSparkline/variantRepeater/imagePreview,
         leaving the dashboard chart blank and the product variant editor with
         no rows at all. --}}
    <script defer src="{{ asset_v('js/shop-admin.js') }}"></script>
    <x-tailwind-cdn :apply-theme="false" />
    <x-accent-style :apply-theme="false" />
</head>
<body class="h-full min-h-full bg-slate-50">
<x-demo-banner />
<x-preview-badge />
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-slate-900 focus:shadow-lg focus:ring-2 focus:ring-brand-500">Skip To Content</a>
<div class="flex min-h-full flex-col">

    {{-- Brand accent hairline --}}
    <div class="h-0.5 bg-gradient-to-r from-brand-600 via-brand-400 to-brand-600"></div>

    {{-- Dark top utility bar (house style) --}}
    <div class="bg-chrome text-sm text-slate-300 ring-1 ring-inset ring-white/5">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-12 items-center justify-between gap-4">
                <x-brand class="text-white" />
                <div class="flex items-center gap-2 sm:gap-3">
                    <span class="hidden items-center gap-1.5 rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs font-medium text-emerald-300 ring-1 ring-inset ring-emerald-400/20 sm:inline-flex">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75 motion-reduce:animate-none"></span>
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        </span>
                        Operational
                    </span>
                    <a href="{{ route('shop.home') }}" target="_blank" rel="noopener"
                        class="hidden h-8 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-slate-300 transition hover:bg-white/10 hover:text-white md:inline-flex">
                        <x-icon name="external" class="h-4 w-4" aria-hidden="true" /> View Store
                    </a>
                    <span class="hidden h-5 w-px bg-white/10 sm:inline-block"></span>
                    <x-dropdown align="right">
                        <x-slot:trigger>
                            <button class="inline-flex items-center gap-2 rounded-full py-1 pl-1 pr-2 transition hover:bg-white/10">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-brand-500/20 text-xs font-semibold text-brand-200 ring-1 ring-brand-400/40">{{ $initials }}</span>
                                <span class="hidden max-w-[8rem] truncate text-xs font-medium text-slate-200 sm:block">{{ \Illuminate\Support\Str::of(auth()->user()?->name ?? 'Admin')->explode(' ')->first() }}</span>
                                <x-icon name="chevron-down" class="h-4 w-4 text-slate-400" aria-hidden="true" />
                            </button>
                        </x-slot:trigger>
                        @auth
                            <div class="border-b border-slate-100 px-3 py-2.5">
                                <p class="truncate text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                            </div>
                        @endauth
                        <x-dropdown-item icon="settings" href="{{ route('settings.index') }}">Settings</x-dropdown-item>
                        @if (auth()->user()?->isAdmin())
                            <x-dropdown-item icon="users" href="{{ route('settings.users.index') }}">Users &amp; Admins</x-dropdown-item>
                            <x-dropdown-item icon="book" href="{{ route('settings.audit.index') }}">Audit Log</x-dropdown-item>
                        @endif
                        <div class="my-1 border-t border-slate-100"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">
                                <x-icon name="x-circle" class="h-4 w-4 shrink-0" aria-hidden="true" /> Sign Out
                            </button>
                        </form>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </div>

    {{-- Main navbar --}}
    <header x-data="{ mobileOpen: false }" class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-14 items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-1">
                    <button type="button" @click="mobileOpen = ! mobileOpen" :aria-expanded="mobileOpen.toString()" aria-label="Toggle Menu"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 lg:hidden">
                        <svg x-show="! mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                        <svg x-show="mobileOpen" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                    <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
                        @foreach ($nav as $item)
                            @if ($item['type'] === 'link')
                                <x-nav-link :href="$item['href']" :icon="$item['icon']" :active="$item['active']">{{ $item['label'] }}</x-nav-link>
                            @else
                                <div x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape="open = false">
                                    <button type="button" @click="open = ! open" :aria-expanded="open.toString()"
                                        @class([
                                            'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium ring-1 ring-inset transition',
                                            'bg-brand-50 text-brand-700 ring-brand-200' => $item['active'],
                                            'text-slate-600 ring-transparent hover:bg-slate-100 hover:text-slate-900 hover:ring-slate-200' => ! $item['active'],
                                        ])>
                                        <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0" aria-hidden="true" />
                                        {{ $item['label'] }}
                                        <x-icon name="chevron-down" class="-mr-0.5 h-4 w-4 text-slate-400 transition-transform" ::class="open && 'rotate-180'" aria-hidden="true" />
                                    </button>
                                    <div x-show="open" x-cloak x-transition
                                         class="absolute left-0 z-40 mt-2 w-56 origin-top-left rounded-lg bg-white py-1 shadow-lg ring-1 ring-slate-200"
                                         @click="open = false">
                                        @foreach ($item['items'] as [$label, $href, $icon, $active])
                                            <a href="{{ $href }}" @class([
                                                'flex items-center gap-2.5 px-3 py-2 text-sm transition',
                                                'bg-brand-50 font-medium text-brand-700' => $active,
                                                'text-slate-700 hover:bg-slate-100' => ! $active,
                                            ])>
                                                <x-icon :name="$icon" class="h-4 w-4 shrink-0 {{ $active ? 'text-brand-600' : 'text-slate-400' }}" aria-hidden="true" /> {{ $label }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </nav>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-button href="{{ route('products.create') }}" icon="plus" size="sm"><span class="hidden sm:inline">New Product</span><span class="sm:hidden">New</span></x-button>
                </div>
            </div>
        </div>
        {{-- Mobile slide-down menu --}}
        <div x-show="mobileOpen" x-cloak
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             class="border-t border-slate-100 bg-white shadow-sm lg:hidden">
            <nav class="{{ $maxWidth }} mx-auto space-y-3 px-4 py-3 sm:px-6" aria-label="Main">
                @foreach ($nav as $item)
                    @if ($item['type'] === 'link')
                        <a href="{{ $item['href'] }}" @class([
                            'flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                            'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200' => $item['active'],
                            'text-slate-600 hover:bg-slate-100' => ! $item['active'],
                        ])>
                            <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0" aria-hidden="true" /> {{ $item['label'] }}
                        </a>
                    @else
                        <div>
                            <p class="vx-eyebrow px-3 pb-2">{{ $item['label'] }}</p>
                            <div class="grid grid-cols-2 gap-1.5">
                                @foreach ($item['items'] as [$label, $href, $icon, $active])
                                    <a href="{{ $href }}" @class([
                                        'flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                                        'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200' => $active,
                                        'text-slate-600 hover:bg-slate-100' => ! $active,
                                    ])>
                                        <x-icon :name="$icon" class="h-4 w-4 shrink-0" aria-hidden="true" /> {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>
        </div>
    </header>

    {{-- Breadcrumbs --}}
    @if (! $isDashboard && count($crumbs))
        <div class="border-b border-slate-200 bg-white">
            <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
                <nav class="flex h-10 items-center gap-2 text-sm" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-slate-500 transition hover:text-brand-700">
                        <x-icon name="home" class="h-4 w-4" aria-hidden="true" /> Dashboard
                    </a>
                    @foreach ($crumbs as $crumb)
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300" aria-hidden="true" />
                        @if ($crumb['href'])
                            <a href="{{ $crumb['href'] }}" class="text-slate-500 transition hover:text-brand-700">{{ $crumb['label'] }}</a>
                        @else
                            <span class="max-w-[18rem] truncate font-medium text-slate-900" aria-current="page">{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </nav>
            </div>
        </div>
    @endif

    {{-- Page content --}}
    <main id="main" class="flex-1 py-8">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
            <x-license-banner class="mb-6" />
            <x-update-banner />
            @if (session('status'))
                <div class="mb-6"><x-alert type="success">{{ session('status') }}</x-alert></div>
            @endif
            @if (session('warning'))
                <div class="mb-6"><x-alert type="warn">{{ session('warning') }}</x-alert></div>
            @endif
            @if (request()->routeIs('settings.*'))
                <div class="settings-shell">
                    <aside class="settings-aside"><x-settings-tabs /></aside>
                    <div>{{ $slot }}</div>
                </div>
            @elseif ($activeGroupItems)
                <div class="settings-shell">
                    <aside class="settings-aside"><x-side-menu :items="$activeGroupItems" /></aside>
                    <div class="min-w-0">{{ $slot }}</div>
                </div>
            @else
                {{ $slot }}
            @endif
        </div>
    </main>

    {{-- Footer --}}
    <footer class="border-t border-slate-200 bg-white">
        <div class="{{ $maxWidth }} mx-auto flex flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-slate-500 sm:px-6 lg:px-8">
            <span>{{ config('brand.name') }} &middot; {{ config('brand.tagline') }}</span>
            <span class="tabular">v{{ \App\Services\UpdateService::currentVersion() }}</span>
        </div>
    </footer>

</div>

{{-- Global tooltip: a single fixed-position element on <body> that reads
     [data-tip]. Fixed positioning means no ancestor's overflow can ever clip it
     (unlike a CSS ::after tip). Supports multi-line tips. --}}
<style>
    .vx-tip{position:fixed;z-index:9999;max-width:22rem;padding:.5rem .625rem;border-radius:.5rem;background:#0f172a;color:#f8fafc;font-size:.75rem;line-height:1.2rem;white-space:pre-line;box-shadow:0 8px 24px rgba(2,6,23,.22);pointer-events:none;opacity:0;transition:opacity .12s ease;display:none}
    .vx-tip strong{color:#fff}
    /* Integrated thin scrollbar for scroll areas (matches the UI, not the OS chrome). */
    .vx-scroll{scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}
    .vx-scroll::-webkit-scrollbar{width:9px;height:9px}
    .vx-scroll::-webkit-scrollbar-track{background:transparent}
    .vx-scroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:9999px;border:2px solid transparent;background-clip:content-box}
    .vx-scroll::-webkit-scrollbar-thumb:hover{background:#94a3b8;background-clip:content-box}
    .vx-scroll::-webkit-scrollbar-corner{background:transparent}
    /* Dialog bodies and file panes must NEVER scroll sideways. Long paths, ids
       and shell one-liners wrap; anything genuinely un-wrappable scrolls inside
       its own .vx-x-scroll box. Setting overflow-y alone would silently make
       overflow-x scroll too. */
    .vx-wrap{overflow-wrap:anywhere}
    .vx-wrap pre,.vx-wrap code{white-space:pre-wrap;overflow-wrap:anywhere}
    .vx-wrap table{width:100%;table-layout:fixed}
    .vx-wrap .vx-x-scroll{overflow-x:auto;max-width:100%}
    /* Inputs carry a ~20ch intrinsic width, which is what pushes two-column
       forms wider than the dialog on narrow viewports. */
    .vx-wrap input,.vx-wrap select,.vx-wrap textarea{min-width:0;max-width:100%}
    .vx-wrap .grid{min-width:0}
</style>
<script>
    (function () {
        var tip;
        function ensure() {
            if (!tip) { tip = document.createElement('div'); tip.className = 'vx-tip'; document.body.appendChild(tip); }
            return tip;
        }
        function show(el) {
            var t = el.getAttribute('data-tip');
            if (!t) return;
            var n = ensure();
            n.textContent = t;
            n.style.display = 'block';
            n.style.opacity = '0';
            var r = el.getBoundingClientRect(), tr = n.getBoundingClientRect();
            var left = Math.max(8, Math.min(r.left + r.width / 2 - tr.width / 2, window.innerWidth - tr.width - 8));
            var top = r.top - tr.height - 8;
            if (top < 8) top = r.bottom + 8; // flip below when there's no room above
            n.style.left = left + 'px';
            n.style.top = top + 'px';
            n.style.opacity = '1';
        }
        function hide() { if (tip) { tip.style.opacity = '0'; tip.style.display = 'none'; } }
        document.addEventListener('mouseover', function (e) { var el = e.target.closest('[data-tip]'); if (el) show(el); });
        document.addEventListener('mouseout', function (e) { var el = e.target.closest('[data-tip]'); if (el) hide(); });
        document.addEventListener('focusin', function (e) { var el = e.target.closest('[data-tip]'); if (el) show(el); });
        document.addEventListener('focusout', hide);
        document.addEventListener('scroll', hide, true);
        window.addEventListener('resize', hide);
    })();
</script>
</body>
</html>
