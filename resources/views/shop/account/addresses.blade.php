<x-layouts.shop title="Addresses">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">My Account</h1>
        <p class="mt-2 text-shop-muted">Manage your orders, profile, and saved addresses.</p>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4 border-b border-shop-line mb-8">
            <div class="flex items-center gap-1 overflow-x-auto no-scrollbar">
                @foreach ([['Orders', 'shop.account', 'bag'], ['Profile', 'shop.account.profile', 'user'], ['Addresses', 'shop.account.addresses', 'home']] as [$label, $routeName, $icon])
                    @php $active = request()->routeIs($routeName); @endphp
                    <a href="{{ route($routeName) }}" class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 -mb-px shrink-0 transition {{ $active ? 'border-brand-600 text-brand-700' : 'border-transparent text-shop-muted hover:text-shop-ink' }}">
                        <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
                    </a>
                @endforeach
            </div>
            <form method="POST" action="{{ route('shop.account.logout') }}" class="shrink-0 mb-2">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-medium text-shop-muted hover:text-rose-600 transition">
                    <x-icon name="x-circle" class="w-4 h-4" /> Sign Out
                </button>
            </form>
        </div>
    </section>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <div class="flex items-center justify-between gap-4 mb-6">
            <h2 class="text-lg font-semibold text-shop-ink">Saved Addresses</h2>
            <x-button icon="plus" x-data @click="$dispatch('open-modal', 'add-address')">Add Address</x-button>
        </div>

        @if ($addresses->isEmpty())
            <x-empty-state icon="home" title="No Saved Addresses" description="Add an address to speed up checkout next time." />
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($addresses as $address)
                    <div class="rounded-xl bg-white ring-1 ring-inset ring-shop-line p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                @if ($address->label)
                                    <p class="text-xs font-medium uppercase tracking-wide text-shop-muted">{{ $address->label }}</p>
                                @endif
                                <p class="font-medium text-shop-ink">{{ $address->first_name }} {{ $address->last_name }}</p>
                            </div>
                            @if ($address->is_default)
                                <x-badge color="info">Default</x-badge>
                            @endif
                        </div>
                        <p class="mt-2 text-sm text-shop-muted leading-relaxed">{{ $address->summary }}</p>
                        <div class="mt-4 flex items-center gap-4">
                            <button type="button" class="text-sm font-medium text-brand-700 hover:text-brand-800 transition" x-data @click="$dispatch('open-modal', 'edit-address-{{ $address->id }}')">Edit</button>
                            <x-confirm-action
                                :name="'delete-address-' . $address->id"
                                :action="route('shop.account.addresses.destroy', $address)"
                                method="DELETE"
                                title="Remove Address?"
                                message="This address will be permanently removed from your account."
                                confirm="Remove"
                                confirmVariant="danger"
                                tone="danger">
                                <button type="button" class="text-sm font-medium text-shop-muted hover:text-rose-600 transition">Remove</button>
                            </x-confirm-action>
                        </div>
                    </div>

                    {{-- Edit modal --}}
                    <x-modal :name="'edit-address-' . $address->id" title="Edit Address" icon="home">
                        <form method="POST" action="{{ route('shop.account.addresses.update', $address) }}" class="space-y-4" id="edit-address-form-{{ $address->id }}">
                            @csrf
                            @method('PUT')
                            <x-field label="Label" hint="E.g. Home, Office">
                                <x-input name="label" value="{{ $address->label }}" />
                            </x-field>
                            <div class="grid grid-cols-2 gap-4">
                                <x-field label="First Name" required><x-input name="first_name" value="{{ $address->first_name }}" required /></x-field>
                                <x-field label="Last Name" required><x-input name="last_name" value="{{ $address->last_name }}" required /></x-field>
                            </div>
                            <x-field label="Company"><x-input name="company" value="{{ $address->company }}" /></x-field>
                            <x-field label="Address Line 1" required><x-input name="line1" value="{{ $address->line1 }}" required /></x-field>
                            <x-field label="Address Line 2"><x-input name="line2" value="{{ $address->line2 }}" /></x-field>
                            <div class="grid grid-cols-2 gap-4">
                                <x-field label="City" required><x-input name="city" value="{{ $address->city }}" required /></x-field>
                                <x-field label="State / Province"><x-input name="state" value="{{ $address->state }}" /></x-field>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <x-field label="Postal Code"><x-input name="postcode" value="{{ $address->postcode }}" /></x-field>
                                <x-field label="Country" required>
                                    <x-select name="country" required>
                                        @foreach (['US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'AU' => 'Australia', 'NZ' => 'New Zealand', 'IE' => 'Ireland', 'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands', 'MX' => 'Mexico'] as $code => $label)
                                            <option value="{{ $code }}" @selected($address->country === $code)>{{ $label }}</option>
                                        @endforeach
                                    </x-select>
                                </x-field>
                            </div>
                            <x-field label="Phone"><x-input type="tel" name="phone" value="{{ $address->phone }}" /></x-field>
                            <x-check-switch name="is_default" value="1" :checked="$address->is_default">Set As Default Address</x-check-switch>
                        </form>
                        <x-slot:footer>
                            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'edit-address-{{ $address->id }}')">Cancel</x-button>
                            <x-button type="submit" form="edit-address-form-{{ $address->id }}">Save Address</x-button>
                        </x-slot:footer>
                    </x-modal>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Add address modal --}}
    <x-modal name="add-address" title="Add Address" icon="home">
        <form method="POST" action="{{ route('shop.account.addresses.store') }}" class="space-y-4" id="add-address-form">
            @csrf
            <x-field label="Label" hint="E.g. Home, Office">
                <x-input name="label" />
            </x-field>
            <div class="grid grid-cols-2 gap-4">
                <x-field label="First Name" required><x-input name="first_name" required /></x-field>
                <x-field label="Last Name" required><x-input name="last_name" required /></x-field>
            </div>
            <x-field label="Company"><x-input name="company" /></x-field>
            <x-field label="Address Line 1" required><x-input name="line1" required /></x-field>
            <x-field label="Address Line 2"><x-input name="line2" /></x-field>
            <div class="grid grid-cols-2 gap-4">
                <x-field label="City" required><x-input name="city" required /></x-field>
                <x-field label="State / Province"><x-input name="state" /></x-field>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-field label="Postal Code"><x-input name="postcode" /></x-field>
                <x-field label="Country" required>
                    <x-select name="country" required>
                        @foreach (['US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'AU' => 'Australia', 'NZ' => 'New Zealand', 'IE' => 'Ireland', 'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands', 'MX' => 'Mexico'] as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </x-select>
                </x-field>
            </div>
            <x-field label="Phone"><x-input type="tel" name="phone" /></x-field>
            <x-check-switch name="is_default" value="1">Set As Default Address</x-check-switch>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'add-address')">Cancel</x-button>
            <x-button type="submit" form="add-address-form">Save Address</x-button>
        </x-slot:footer>
    </x-modal>

</x-layouts.shop>
