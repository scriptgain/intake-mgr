<x-layouts.shop-auth
    title="Set A New Password"
    heading="Set A New Password"
    subheading="Choose a new password for your account."
    :store-name="$storeName"
    :theme-logo="$themeLogo"
>
    <form method="POST" action="{{ route('shop.account.reset.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-field label="Email" for="email" required :error="$errors->first('email')">
            <x-input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" />
        </x-field>

        <x-field label="New Password" for="password" required hint="At Least 8 Characters." :error="$errors->first('password')">
            <div x-data="{ show: false }" class="relative">
                <x-input type="password" id="password" name="password" required autofocus autocomplete="new-password" class="pr-11"
                         x-bind:type="show ? 'text' : 'password'" />
                <button type="button" @click="show = !show" :aria-label="show ? 'Hide Password' : 'Show Password'"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-shop-muted transition hover:text-shop-ink focus-visible:outline-none focus-visible:text-shop-ink">
                    <x-icon name="eye" class="h-5 w-5" />
                </button>
            </div>
        </x-field>

        <x-field label="Confirm New Password" for="password_confirmation" required>
            <x-input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
        </x-field>

        <x-captcha surface="account_forgot" />
        <x-button type="submit" size="lg" class="w-full justify-center">Reset Password</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-shop-muted">
        <a href="{{ route('shop.account.login') }}" class="font-medium text-brand-700 hover:underline">Back To Sign In</a>
    </p>
</x-layouts.shop-auth>
