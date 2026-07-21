<x-layouts.app title="New Shipping Zone">
    <x-page-header title="New Shipping Zone" icon="truck" subtitle="Group the countries this zone covers, then add rates once it's saved.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('shipping.index') }}" icon="chevron-left">Back To Shipping</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('shipping.store') }}" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field label="Zone Name" for="name" required :error="$errors->first('name')" class="sm:col-span-2">
                    <x-input id="name" name="name" :value="old('name')" required autofocus placeholder="United States" />
                </x-field>
                <x-field label="Countries" for="countries" required hint="Comma-separated ISO-2 codes, or * for the rest of the world." :error="$errors->first('countries')">
                    <x-input id="countries" name="countries" :value="old('countries', implode(', ', $zone->countries ?? ['US']))" required placeholder="US, CA" />
                </x-field>
                <x-field label="States / Provinces" for="states" hint="Optional. Blank covers the whole country." :error="$errors->first('states')">
                    <x-input id="states" name="states" :value="old('states')" placeholder="AZ, CA, NV" />
                </x-field>
            </div>
            <x-toggle name="is_active" :checked="old('is_active', true)" label="Active" description="Inactive zones are not offered at checkout." />
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <x-button variant="secondary" href="{{ route('shipping.index') }}">Cancel</x-button>
                <x-button type="submit" icon="check">Create Zone</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
