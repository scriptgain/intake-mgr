<x-layouts.shop title="Profile">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">My Account</h1>
        <p class="mt-2 text-shop-muted">Manage your orders, profile, and saved addresses.</p>
    </section>

    <x-account-tabs />

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <div>
            <x-card title="Profile Details">
                <form method="POST" action="{{ route('shop.account.profile.update') }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-2 gap-4">
                        <x-field label="First Name" for="first_name" required>
                            <x-input id="first_name" name="first_name" value="{{ old('first_name', $customer->first_name) }}" required />
                        </x-field>
                        <x-field label="Last Name" for="last_name" required>
                            <x-input id="last_name" name="last_name" value="{{ old('last_name', $customer->last_name) }}" required />
                        </x-field>
                    </div>
                    <x-field label="Email" for="email" required>
                        <x-input type="email" id="email" name="email" value="{{ old('email', $customer->email) }}" required />
                    </x-field>
                    <x-field label="Phone" for="phone">
                        <x-input type="tel" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" />
                    </x-field>
                    <div class="pt-2 border-t border-shop-line"></div>
                    <x-field label="New Password" for="password" hint="Leave blank to keep your current password.">
                        <x-input type="password" id="password" name="password" autocomplete="new-password" />
                    </x-field>
                    <x-field label="Confirm New Password" for="password_confirmation">
                        <x-input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
                    </x-field>
                    <x-check-switch name="accepts_marketing" value="1" :checked="$customer->accepts_marketing">Email Me About New Arrivals And Offers</x-check-switch>
                    <div class="flex justify-end">
                        <x-button type="submit">Save Changes</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </section>

</x-layouts.shop>
