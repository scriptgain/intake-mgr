<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In: {{ config('brand.name') }}</title>
    <x-tailwind-cdn />
    <x-accent-style />
</head>
<body class="h-full bg-slate-50">
<div class="min-h-full flex flex-col lg:flex-row">

    {{-- Brand panel --}}
    <div class="lg:w-1/2 bg-chrome text-white px-8 py-12 lg:p-16 flex flex-col justify-between">
        <x-brand class="text-white" />
        <div class="hidden lg:block max-w-md">
            <h2 class="text-3xl font-semibold tracking-tight">Your store, on your own server.</h2>
            <p class="mt-4 text-slate-300 leading-relaxed">
                One panel for your whole catalog. Products, orders, customers, discounts,
                shipping and tax: with a storefront your customers actually enjoy buying from.
            </p>
            <ul class="mt-8 space-y-3 text-sm text-slate-300">
                <li class="flex items-center gap-2"><x-icon name="check-circle" class="w-5 h-5 text-brand-400" /> Variants, inventory &amp; collections</li>
                <li class="flex items-center gap-2"><x-icon name="check-circle" class="w-5 h-5 text-brand-400" /> Orders, fulfillment &amp; refunds</li>
                <li class="flex items-center gap-2"><x-icon name="check-circle" class="w-5 h-5 text-brand-400" /> Discounts, shipping zones &amp; tax rules</li>
            </ul>
        </div>
        <p class="text-xs text-slate-400">{{ config('brand.name') }} &middot; {{ config('brand.tagline') }}</p>
    </div>

    {{-- Form panel --}}
    <div class="lg:w-1/2 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Sign In</h1>
            <p class="mt-1 text-sm text-slate-500">Welcome back. Enter your credentials to continue.</p>

            @if ($errors->any())
                <div class="mt-6">
                    <x-alert type="danger">{{ $errors->first() }}</x-alert>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                @csrf
                <x-field label="Email" for="email" required>
                    <x-input id="email" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com" />
                </x-field>
                <x-field label="Password" for="password" required>
                    <x-input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" />
                </x-field>

                <x-toggle name="remember" label="Remember Me" />

                <x-captcha surface="admin_login" />

                <x-button type="submit" class="w-full">Sign In</x-button>
            </form>
            @if (($devLoginUser ?? null) || ! empty($demoStaff ?? []))
                {{-- Only rendered when the request IP matches the dev_login_ip
                     setting. Every endpoint enforces the same check, so this is
                     a convenience, not the security boundary. --}}
                <div class="mt-6 section-divider pt-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Demo Logins</p>
                    <div class="mt-3 space-y-2">
                        @if ($devLoginUser ?? null)
                            <form method="POST" action="{{ route('dev-login') }}">
                                @csrf
                                <x-button type="submit" variant="secondary" icon="user" class="w-full !justify-start">
                                    Sign In As {{ $devLoginUser->name }}
                                </x-button>
                            </form>
                        @endif
                        @foreach ($demoStaff ?? [] as $persona)
                            <form method="POST" action="{{ route('demo-login.staff', $persona['key']) }}">
                                @csrf
                                <x-button type="submit" variant="secondary" icon="user" class="w-full !justify-start">
                                    <span class="flex-1 text-left">{{ $persona['label'] }}</span>
                                    <span class="text-xs font-normal text-slate-400 ring-1 ring-inset ring-slate-200 rounded-full px-2 py-0.5">{{ $persona['note'] }}</span>
                                </x-button>
                            </form>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Visible only from your allowlisted IP address.</p>
                </div>
            @endif

        </div>
    </div>

</div>
</body>
</html>
