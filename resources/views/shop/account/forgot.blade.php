<x-layouts.shop-auth
    title="Forgot Password"
    heading="Reset Your Password"
    subheading="Enter your email and we'll send a link to set a new password."
    :store-name="$storeName"
    :theme-logo="$themeLogo"
>
    <form method="POST" action="{{ route('shop.account.forgot') }}" class="mt-8 space-y-5">
        @csrf
        <x-field label="Email" for="email" required :error="$errors->first('email')">
            <x-input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" />
        </x-field>

        <x-captcha surface="account_forgot" />
        <x-button type="submit" size="lg" class="w-full justify-center">Email A Reset Link</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-shop-muted">
        Remembered It? <a href="{{ route('shop.account.login') }}" class="font-medium text-brand-700 hover:underline">Back To Sign In</a>
    </p>
</x-layouts.shop-auth>
