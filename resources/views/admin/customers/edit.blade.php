<x-layouts.app title="Edit Customer">
    <x-page-header title="Edit Customer" icon="user" :subtitle="$customer->email">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('customers.show', $customer) }}" icon="chevron-left">Back To Customer</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field label="First Name" for="first_name" :error="$errors->first('first_name')">
                    <x-input id="first_name" name="first_name" :value="old('first_name', $customer->first_name)" />
                </x-field>
                <x-field label="Last Name" for="last_name" :error="$errors->first('last_name')">
                    <x-input id="last_name" name="last_name" :value="old('last_name', $customer->last_name)" />
                </x-field>
                <x-field label="Email" for="email" required :error="$errors->first('email')">
                    <x-input id="email" name="email" type="email" :value="old('email', $customer->email)" required />
                </x-field>
                <x-field label="Phone" for="phone" :error="$errors->first('phone')">
                    <x-input id="phone" name="phone" :value="old('phone', $customer->phone)" />
                </x-field>
            </div>
            <x-field label="Notes" for="notes" hint="Visible to staff only." :error="$errors->first('notes')">
                <textarea id="notes" name="notes" rows="4"
                    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('notes', $customer->notes) }}</textarea>
            </x-field>
            <x-toggle name="accepts_marketing" :checked="old('accepts_marketing', $customer->accepts_marketing)"
                label="Accepts Marketing" description="Subscribed to promotional emails." />
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <x-button variant="secondary" href="{{ route('customers.show', $customer) }}">Cancel</x-button>
                <x-button type="submit" icon="check">Save Changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
