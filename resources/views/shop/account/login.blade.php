<x-layouts.shop-auth
    title="Sign In"
    heading="Welcome Back"
    subheading="Sign in to view your orders and saved details."
    :store-name="$storeName"
    :theme-logo="$themeLogo"
>
    <form method="POST" action="{{ route('shop.account.login') }}" class="mt-8 space-y-5">
        @csrf
        <x-field label="Email" for="email" required :error="$errors->first('email')">
            <x-input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" />
        </x-field>

        <x-field label="Password" for="password" required>
            <div x-data="{ show: false }" class="relative">
                <x-input type="password" id="password" name="password" required autocomplete="current-password" class="pr-11"
                         x-bind:type="show ? 'text' : 'password'" />
                <button type="button" @click="show = !show" :aria-label="show ? 'Hide Password' : 'Show Password'"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-shop-muted transition hover:text-shop-ink focus-visible:outline-none focus-visible:text-shop-ink">
                    <x-icon name="eye" class="h-5 w-5" />
                </button>
            </div>
        </x-field>

        <div class="flex items-center justify-between gap-4">
            <x-toggle name="remember" label="Keep Me Signed In" />
            <a href="{{ route('shop.account.forgot') }}" class="text-sm font-medium text-brand-700 hover:underline">Forgot Password?</a>
        </div>

        <x-captcha surface="account_login" />
        <x-button type="submit" size="lg" class="w-full justify-center">Sign In</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-shop-muted">
        Don't Have An Account? <a href="{{ route('shop.account.register') }}" class="font-medium text-brand-700 hover:underline">Create One</a>
    </p>

    @if (! empty($demoCustomers ?? []))
        {{-- IP-gated demo persona picker. Rendered only from the allowlisted IP;
             each endpoint re-checks the IP server-side, so this is a convenience,
             not the security boundary. --}}
        <div class="mt-10 border-t border-shop-line pt-8">
            <p class="text-center text-xs font-semibold uppercase tracking-[0.16em] text-shop-muted">Demo Logins</p>
            <div class="mt-4 grid gap-2">
                @foreach ($demoCustomers as $persona)
                    <form method="POST" action="{{ route('shop.account.demo-login', $persona['key']) }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-lg bg-white px-4 py-3 text-left ring-1 ring-inset ring-shop-line transition hover:bg-brand-50/40 hover:ring-brand-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700 ring-1 ring-inset ring-brand-200">{{ $persona['model']->initials }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-shop-ink">{{ $persona['label'] }}</span>
                                <span class="block text-xs text-shop-muted">{{ $persona['note'] }}</span>
                            </span>
                            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-shop-muted" />
                        </button>
                    </form>
                @endforeach

                {{-- One-click staff login (same IP gate) — jumps into the admin panel. --}}
                <form method="POST" action="{{ route('dev-login') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg bg-shop-ink/[0.03] px-4 py-3 text-left ring-1 ring-inset ring-shop-line transition hover:bg-brand-50/40 hover:ring-brand-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-shop-ink text-sm font-semibold text-white">
                            <x-icon name="lock" class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-shop-ink">Staff / Admin Panel</span>
                            <span class="block text-xs text-shop-muted">Sign in as an administrator</span>
                        </span>
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-shop-muted" />
                    </button>
                </form>
            </div>
            <p class="mt-3 text-center text-xs text-shop-muted">Visible only from your allowlisted IP address.</p>
        </div>
    @endif
</x-layouts.shop-auth>
