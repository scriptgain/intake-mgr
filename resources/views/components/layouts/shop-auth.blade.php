@props([
    'title' => null,
    'heading' => null,
    'subheading' => null,
    'storeName' => null,
    'themeLogo' => null,
])
{{-- Full-screen storefront auth shell: a branded panel on the left, the form on
     the right. Deliberately NOT the storefront chrome (no nav, no footer); the
     only way back to the shop is the wordmark, which links home. Rendered by the
     account auth views, which pass storeName/themeLogo down from the shop view
     composer. --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' : '.$storeName : $storeName }}</title>
    <x-favicon-links />
    <x-tailwind-cdn />
    <x-accent-style />
</head>
<body class="h-full min-h-full bg-white text-shop-ink antialiased">
<div class="min-h-screen lg:grid lg:grid-cols-[1.05fr_1fr]">

    {{-- Brand panel. The one bold surface: an oversized wordmark, the store's
         promise, and the reassurances that get a first-time shopper to trust an
         account. Hidden on small screens, where a compact header stands in. --}}
    <aside class="relative hidden overflow-hidden bg-brand-700 lg:flex lg:flex-col">
        {{-- Layered light so the flat brand fill reads as depth, not a slab. --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(120%_90%_at_12%_8%,var(--color-brand-500)_0%,transparent_55%)]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(90%_80%_at_100%_100%,var(--color-brand-900)_0%,transparent_60%)]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(255,255,255,0.06),transparent_30%,rgba(0,0,0,0.18))]"></div>
        {{-- Fine dot field for texture. --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-[0.14] bg-[radial-gradient(rgba(255,255,255,0.9)_1px,transparent_1.4px)] [background-size:22px_22px]"></div>

        <div class="relative flex flex-1 flex-col justify-between p-10 xl:p-14">
            <a href="{{ route('shop.home') }}" class="inline-flex items-center gap-2.5 self-start rounded-lg text-white transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-brand-700">
                @if ($themeLogo)
                    <img src="{{ $themeLogo }}" alt="{{ $storeName }}" class="h-9 max-w-[13rem] object-contain">
                @else
                    <x-icon name="bag" class="h-8 w-8 shrink-0" />
                    <span class="text-xl font-semibold tracking-tight">{{ $storeName }}</span>
                @endif
            </a>

            <div class="max-w-md py-14">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-white/70">{{ $storeName }} Account</p>
                <h2 class="mt-4 text-4xl font-semibold leading-[1.1] tracking-tight text-white xl:text-5xl">
                    Your requests, work orders, and invoices, all in one place.
                </h2>
                <p class="mt-5 text-lg leading-relaxed text-white/80">
                    Sign in to track every order, breeze through checkout with saved details, and pick up right where you left off.
                </p>

                <ul class="mt-10 space-y-4">
                    @foreach ([
                        ['truck', 'Free Shipping', 'On Every Order Over $75'],
                        ['refresh', 'Easy 30-Day Returns', 'Changed Your Mind? No Fuss'],
                        ['lock', 'Secure Checkout', 'Payments Encrypted End To End'],
                    ] as [$icon, $label, $note])
                        <li class="flex items-start gap-3.5">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white ring-1 ring-inset ring-white/20">
                                <x-icon :name="$icon" class="h-5 w-5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-white">{{ $label }}</span>
                                <span class="block text-sm text-white/70">{{ $note }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="text-sm text-white/60">Trusted by neighbors for fast, reliable local service.</p>
        </div>
    </aside>

    {{-- Form column. Everything here stays quiet so the brand panel keeps the
         one loud voice. --}}
    <main class="flex min-h-screen flex-col bg-white">
        {{-- Compact header: the wordmark home link (mobile substitute for the
             panel) and an escape hatch back to the store. --}}
        <div class="flex items-center justify-between px-6 py-6 sm:px-10">
            <a href="{{ route('shop.home') }}" class="inline-flex items-center gap-2 text-shop-ink transition hover:text-brand-700 lg:invisible">
                <x-icon name="bag" class="h-6 w-6 shrink-0 text-brand-600" />
                <span class="text-lg font-semibold tracking-tight">{{ $storeName }}</span>
            </a>
            <a href="{{ route('shop.home') }}" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-sm font-medium text-shop-muted transition hover:text-shop-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60">
                <x-icon name="chevron-left" class="h-4 w-4 shrink-0" />
                Back To Store
            </a>
        </div>

        <div class="flex flex-1 items-center justify-center px-6 pb-16 pt-4 sm:px-10">
            <div class="w-full max-w-md">
                @if ($heading)
                    <h1 class="text-3xl font-semibold tracking-tight text-shop-ink">{{ $heading }}</h1>
                @endif
                @if ($subheading)
                    <p class="mt-2 text-shop-muted">{{ $subheading }}</p>
                @endif

                @if (session('status'))
                    <div class="mt-6">
                        <x-alert type="success">{{ session('status') }}</x-alert>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </div>

        <footer class="px-6 pb-8 sm:px-10">
            <p class="text-center text-xs text-shop-muted">&copy; {{ date('Y') }} {{ $storeName }}. All Rights Reserved.</p>
        </footer>
    </main>

</div>
</body>
</html>
