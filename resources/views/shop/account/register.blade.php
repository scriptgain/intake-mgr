<x-layouts.shop-auth
    title="Create Account"
    heading="Create Your Account"
    subheading="Save your details for faster checkout next time."
    :store-name="$storeName"
    :theme-logo="$themeLogo"
>
    <form method="POST" action="{{ route('shop.account.register') }}" class="mt-8 space-y-5">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <x-field label="First Name" for="first_name" required :error="$errors->first('first_name')">
                <x-input id="first_name" name="first_name" value="{{ old('first_name') }}" required autofocus autocomplete="given-name" />
            </x-field>
            <x-field label="Last Name" for="last_name" required :error="$errors->first('last_name')">
                <x-input id="last_name" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name" />
            </x-field>
        </div>

        <x-field label="Email" for="email" required :error="$errors->first('email')">
            <x-input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" />
        </x-field>

        <x-field label="Password" for="password" required hint="At Least 8 Characters." :error="$errors->first('password')">
            <div x-data="{ show: false }" class="relative">
                <x-input type="password" id="password" name="password" required autocomplete="new-password" class="pr-11"
                         x-bind:type="show ? 'text' : 'password'" />
                <button type="button" @click="show = !show" :aria-label="show ? 'Hide Password' : 'Show Password'"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-shop-muted transition hover:text-shop-ink focus-visible:outline-none focus-visible:text-shop-ink">
                    <x-icon name="eye" class="h-5 w-5" />
                </button>
            </div>
        </x-field>

        <x-field label="Confirm Password" for="password_confirmation" required>
            <x-input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
        </x-field>

        <x-check-switch name="accepts_marketing" value="1">Email Me About New Arrivals And Offers</x-check-switch>
        <x-captcha surface="account_register" />
        <x-button type="submit" size="lg" class="w-full justify-center">Create Account</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-shop-muted">
        Already Have An Account? <a href="{{ route('shop.account.login') }}" class="font-medium text-brand-700 hover:underline">Sign In</a>
    </p>
</x-layouts.shop-auth>
