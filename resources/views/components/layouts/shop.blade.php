@props(['title' => null, 'maxWidth' => null])
@php
    $maxWidth = $maxWidth ?? config('shop.max_width', 'max-w-6xl');
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Title, description, canonical, robots, Open Graph, Twitter and JSON-LD.
         All of it comes from SeoService via App\View\Components\Seo; no view
         writes a meta tag by hand. --}}
    <x-seo :fallback-title="$title" />
    <x-favicon-links />
    {{-- Must precede the Alpine CDN in x-tailwind-cdn. Deferred scripts run in
         document order, so Alpine loaded first would fire alpine:init before
         this file could register variantPicker/quantityStepper, leaving the
         buy box with no price and no variant_id. --}}
    <script src="{{ asset_v('js/storefront.js') }}" defer></script>
    <x-tailwind-cdn />
    <x-accent-style />
</head>
<body class="h-full min-h-full shop-body">
<x-preview-badge />
<div x-data="{ mobileOpen: false }" class="min-h-full flex flex-col">

    {{-- Dark top utility bar (house style): tagline + account/cart quick links. --}}
    <div class="bg-chrome text-slate-300 text-xs sm:text-sm">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-10 items-center justify-between gap-4">
                <p class="truncate text-slate-400">{{ config('shop.store_tagline') }}</p>
                <div class="flex items-center gap-4 shrink-0">
                    @if ($currentCustomer)
                        <a href="{{ route('shop.account') }}" class="inline-flex items-center gap-1.5 rounded px-2 py-0.5 ring-1 ring-transparent hover:text-white hover:ring-white/30 transition truncate max-w-[10rem]">
                            <x-icon name="user" class="w-3.5 h-3.5 shrink-0" /> Hi, {{ $currentCustomer->first_name }}
                        </a>
                    @else
                        <a href="{{ route('shop.account.login') }}" class="inline-flex items-center gap-1.5 rounded px-2 py-0.5 ring-1 ring-transparent hover:text-white hover:ring-white/30 transition">
                            <x-icon name="user" class="w-3.5 h-3.5 shrink-0" /> Sign In
                        </a>
                        <a href="{{ route('shop.account.register') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded px-2 py-0.5 ring-1 ring-transparent hover:text-white hover:ring-white/30 transition">
                            <x-icon name="plus" class="w-3.5 h-3.5 shrink-0" /> Create Account
                        </a>
                    @endif
                    <a href="{{ route('shop.cart') }}" class="inline-flex items-center gap-1.5 rounded px-2 py-0.5 ring-1 ring-transparent hover:text-white hover:ring-white/30 transition">
                        <x-icon name="bag" class="w-3.5 h-3.5 shrink-0" /> Cart ({{ $cartCount }})
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Main navbar (light, sticky, separate from the utility bar). No chip/box
         behind the logo, per house style. --}}
    <header class="bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 border-b border-shop-line sticky top-0 z-30">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between gap-4">
                <div class="flex items-center gap-2 min-w-0">
                    <button type="button" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-label="Toggle Menu"
                        class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg text-shop-ink hover:bg-slate-100 transition shrink-0">
                        <svg x-show="!mobileOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                        <svg x-show="mobileOpen" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                    {{-- Wordmark: the mark sits bare in the brand accent. No chip,
                         box, or background behind the logo (house style). --}}
                    <a href="{{ route('shop.home') }}" class="inline-flex items-center gap-2 shrink-0">
                        @if ($themeLogo)
                            <img src="{{ $themeLogo }}" alt="{{ $storeName }}" class="h-8 max-w-[11rem] object-contain">
                        @else
                            <x-icon name="bag" class="w-6 h-6 text-brand-600 shrink-0" />
                            <span class="text-lg font-semibold tracking-tight text-shop-ink truncate max-w-[10rem] sm:max-w-none">{{ $storeName }}</span>
                        @endif
                    </a>
                    <nav class="hidden lg:flex items-center gap-1 ml-4">
                        @php($catalogActive = request()->routeIs('shop.catalog'))
                        <a href="{{ route('shop.catalog') }}"
                           @if ($catalogActive) aria-current="page" @endif
                           @class([
                               'group inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium ring-1 transition',
                               'bg-brand-50 text-brand-700 ring-brand-200 font-semibold' => $catalogActive,
                               'text-shop-ink/80 ring-transparent hover:text-shop-ink hover:bg-slate-100 hover:ring-slate-200' => ! $catalogActive,
                           ])>
                            <x-icon name="box" @class(['w-4 h-4 shrink-0 transition-colors', 'text-brand-600' => $catalogActive, 'text-shop-muted group-hover:text-brand-600' => ! $catalogActive]) /> All Products
                        </a>
                        @foreach ($navCollections as $navCollection)
                            @php($active = url()->current() === route('shop.collection', $navCollection))
                            <a href="{{ route('shop.collection', $navCollection) }}"
                               @if ($active) aria-current="page" @endif
                               @class([
                                   'group inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium ring-1 transition',
                                   'bg-brand-50 text-brand-700 ring-brand-200 font-semibold' => $active,
                                   'text-shop-ink/80 ring-transparent hover:text-shop-ink hover:bg-slate-100 hover:ring-slate-200' => ! $active,
                               ])>
                                <x-icon name="tag" @class(['w-4 h-4 shrink-0 transition-colors', 'text-brand-600' => $active, 'text-shop-muted group-hover:text-brand-600' => ! $active]) /> {{ $navCollection->name }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <form action="{{ route('shop.search') }}" method="GET" class="hidden md:block">
                        <label class="relative">
                            <span class="sr-only">Search Products</span>
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-shop-muted" />
                            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search Products"
                                class="w-48 lg:w-64 rounded-full border border-slate-200 bg-white pl-9 pr-4 py-2 text-sm text-shop-ink placeholder:text-shop-muted focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 transition">
                        </label>
                    </form>
                    <a href="{{ route('shop.search') }}" class="md:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg text-shop-ink hover:bg-slate-100 transition" aria-label="Search">
                        <x-icon name="search" class="w-5 h-5" />
                    </a>
                    <a href="{{ $currentCustomer ? route('shop.account') : route('shop.account.login') }}" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-shop-ink hover:bg-slate-100 transition" aria-label="Account">
                        <x-icon name="user" class="w-5 h-5" />
                    </a>
                    <a href="{{ route('shop.cart') }}" class="relative inline-flex items-center justify-center w-9 h-9 rounded-lg text-shop-ink hover:bg-slate-100 transition" aria-label="Cart">
                        <x-icon name="bag" class="w-5 h-5" />
                        @if ($cartCount > 0)
                            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[1.1rem] h-[1.1rem] rounded-full bg-brand-600 text-white text-[10px] font-semibold px-1">{{ $cartCount }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </div>

        {{-- Mobile slide-down menu. --}}
        <div x-show="mobileOpen" x-cloak
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             class="lg:hidden border-t border-shop-line bg-white shadow-sm">
            <nav class="{{ $maxWidth }} mx-auto px-4 sm:px-6 py-4 space-y-1">
                <form action="{{ route('shop.search') }}" method="GET" class="mb-3">
                    <label class="relative block">
                        <span class="sr-only">Search Products</span>
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-shop-muted" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search Products"
                            class="w-full rounded-full border border-slate-200 bg-white pl-9 pr-4 py-2 text-sm text-shop-ink placeholder:text-shop-muted focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    </label>
                </form>
                <a href="{{ route('shop.catalog') }}" class="block px-3 py-2.5 rounded-lg text-sm font-medium text-shop-ink hover:bg-slate-100 transition">All Products</a>
                @foreach ($navCollections as $navCollection)
                    <a href="{{ route('shop.collection', $navCollection) }}" class="block px-3 py-2.5 rounded-lg text-sm font-medium text-shop-ink hover:bg-slate-100 transition">{{ $navCollection->name }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    {{-- Page content --}}
    <main class="flex-1">
        @if (session('status') || session('warning') || $errors->any())
            <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-3">
                @if (session('status'))
                    <x-alert type="success">{{ session('status') }}</x-alert>
                @endif
                @if (session('warning'))
                    <x-alert type="warn">{{ session('warning') }}</x-alert>
                @endif
                @if ($errors->any())
                    <x-alert type="danger" title="There Was A Problem">
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif
            </div>
        @endif

        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="border-t border-shop-line bg-white">
        {{-- Trust strip: the reassurances that get a cart over the line. --}}
        <div class="border-b border-shop-line bg-slate-50">
            <div class="{{ $maxWidth }} mx-auto grid grid-cols-2 gap-px px-4 sm:px-6 lg:px-8 lg:grid-cols-4">
                <div class="flex items-center gap-3 py-5 lg:px-4 lg:first:pl-0 lg:last:pr-0">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 ring-1 ring-inset ring-brand-200">
                        <x-icon name="truck" class="w-5 h-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-shop-ink">Free Shipping</span>
                        <span class="block text-xs text-shop-muted">On Orders Over $75</span>
                    </span>
                </div>
                <div class="flex items-center gap-3 py-5 lg:px-4 lg:first:pl-0 lg:last:pr-0">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 ring-1 ring-inset ring-brand-200">
                        <x-icon name="refresh" class="w-5 h-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-shop-ink">Easy Returns</span>
                        <span class="block text-xs text-shop-muted">30 Days, No Fuss</span>
                    </span>
                </div>
                <div class="flex items-center gap-3 py-5 lg:px-4 lg:first:pl-0 lg:last:pr-0">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 ring-1 ring-inset ring-brand-200">
                        <x-icon name="lock" class="w-5 h-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-shop-ink">Secure Checkout</span>
                        <span class="block text-xs text-shop-muted">Encrypted Payments</span>
                    </span>
                </div>
                <div class="flex items-center gap-3 py-5 lg:px-4 lg:first:pl-0 lg:last:pr-0">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 ring-1 ring-inset ring-brand-200">
                        <x-icon name="shield" class="w-5 h-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-shop-ink">Two-Year Guarantee</span>
                        <span class="block text-xs text-shop-muted">On Everything We Sell</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
                {{-- Brand + newsletter, the widest column --}}
                <div class="min-w-0 lg:col-span-4">
                    <a href="{{ route('shop.home') }}" class="inline-flex items-center gap-2">
                        <x-icon name="bag" class="w-6 h-6 shrink-0 text-brand-600" />
                        <span class="text-lg font-semibold tracking-tight text-shop-ink">{{ $storeName }}</span>
                    </a>
                    <p class="mt-3 max-w-sm text-sm leading-relaxed text-shop-muted">{{ config('shop.store_tagline') }}</p>

                    <p class="mt-6 text-sm font-semibold text-shop-ink">Get 10% Off Your First Order</p>
                    <form action="{{ route('shop.search') }}" method="GET" class="mt-3 flex max-w-sm gap-2">
                        <label for="footer-news" class="sr-only">Email Address</label>
                        <div class="relative flex-1">
                            <x-icon name="envelope" class="pointer-events-none absolute left-3 top-1/2 w-4 h-4 -translate-y-1/2 text-shop-muted" />
                            <input id="footer-news" type="email" name="email" placeholder="Email Address"
                                   class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-shop-ink placeholder:text-shop-muted focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 transition">
                        </div>
                        <button type="submit" class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">
                            Subscribe
                        </button>
                    </form>
                </div>

                <div class="min-w-0 lg:col-span-2">
                    <p class="text-sm font-semibold text-shop-ink">Shop</p>
                    <ul class="mt-4 space-y-2.5 text-sm text-shop-muted">
                        <li><a href="{{ route('shop.catalog') }}" class="hover:text-shop-ink transition">All Products</a></li>
                        <li><a href="{{ route('shop.collections') }}" class="hover:text-shop-ink transition">Collections</a></li>
                        <li><a href="{{ route('shop.cart') }}" class="hover:text-shop-ink transition">Your Cart</a></li>
                    </ul>
                </div>

                <div class="min-w-0 lg:col-span-2">
                    <p class="text-sm font-semibold text-shop-ink">Account</p>
                    <ul class="mt-4 space-y-2.5 text-sm text-shop-muted">
                        @if ($currentCustomer)
                            <li><a href="{{ route('shop.account') }}" class="hover:text-shop-ink transition">My Account</a></li>
                            <li><a href="{{ route('shop.account') }}" class="hover:text-shop-ink transition">Order History</a></li>
                            <li><a href="{{ route('shop.account.addresses') }}" class="hover:text-shop-ink transition">Addresses</a></li>
                        @else
                            <li><a href="{{ route('shop.account.login') }}" class="hover:text-shop-ink transition">Sign In</a></li>
                            <li><a href="{{ route('shop.account.register') }}" class="hover:text-shop-ink transition">Create Account</a></li>
                        @endif
                    </ul>
                </div>

                <div class="min-w-0 lg:col-span-2">
                    <p class="text-sm font-semibold text-shop-ink">Help</p>
                    <ul class="mt-4 space-y-2.5 text-sm text-shop-muted">
                        <li><a href="{{ route('shop.help') }}" class="hover:text-shop-ink transition">Help Center</a></li>
                        <li><a href="{{ route('shop.page', 'shipping') }}" class="hover:text-shop-ink transition">Shipping Information</a></li>
                        <li><a href="{{ route('shop.page', 'refund-policy') }}" class="hover:text-shop-ink transition">Refund Policy</a></li>
                        @if (config('shop.store_email'))
                            <li><a href="mailto:{{ config('shop.store_email') }}" class="hover:text-shop-ink transition">Contact Us</a></li>
                        @endif
                    </ul>
                </div>

                <div class="min-w-0 lg:col-span-2">
                    <p class="text-sm font-semibold text-shop-ink">Legal</p>
                    <ul class="mt-4 space-y-2.5 text-sm text-shop-muted">
                        <li><a href="{{ route('shop.page', 'terms') }}" class="hover:text-shop-ink transition">Terms Of Service</a></li>
                        <li><a href="{{ route('shop.page', 'privacy') }}" class="hover:text-shop-ink transition">Privacy Policy</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-10 flex flex-col gap-4 border-t border-shop-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                <span class="text-xs text-shop-muted">&copy; {{ date('Y') }} {{ $storeName }}. All Rights Reserved.</span>
                {{-- Payment method chips: the "you can pay" reassurance carts expect. --}}
                <div class="flex flex-wrap items-center gap-2" aria-label="Accepted Payment Methods">
                    @foreach (['Visa', 'Mastercard', 'Amex', 'Discover', 'PayPal'] as $method)
                        <span class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-shop-muted">
                            <x-icon name="credit-card" class="w-3.5 h-3.5 shrink-0" /> {{ $method }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </footer>

</div>

{{-- Global tooltip: a single fixed-position element on <body> that reads
     [data-tip]. Fixed positioning means no ancestor's overflow can ever clip
     it (unlike a CSS ::after tip). --}}
<style>
    .vx-tip{position:fixed;z-index:9999;max-width:22rem;padding:.5rem .625rem;border-radius:.5rem;background:#0f172a;color:#f8fafc;font-size:.75rem;line-height:1.2rem;white-space:pre-line;box-shadow:0 8px 24px rgba(2,6,23,.22);pointer-events:none;opacity:0;transition:opacity .12s ease;display:none}
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
            if (top < 8) top = r.bottom + 8;
            n.style.left = left + 'px';
            n.style.top = top + 'px';
            n.style.opacity = '1';
        }
        function hide() { if (tip) { tip.style.opacity = '0'; tip.style.display = 'none'; } }
        document.addEventListener('mouseover', function (e) { var el = e.target.closest('[data-tip]'); if (el) show(el); });
        document.addEventListener('mouseout', function (e) { var el = e.target.closest('[data-tip]'); if (el) hide(); });
        document.addEventListener('scroll', hide, true);
        window.addEventListener('resize', hide);
    })();

    // Submit feedback: any form submit puts a spinner on its submit button so
    // an action (Remove, Update, checkout, save) never sits silently. Opt out
    // with data-no-loader on the form.
    (function () {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.hasAttribute('data-no-loader')) return;
            var btn = form.querySelector('[type="submit"]');
            if (!btn || btn.classList.contains('is-loading')) return;
            btn.classList.add('is-loading');
            setTimeout(function () { btn.disabled = true; }, 0);
        }, true);
    })();
</script>
</body>
</html>
