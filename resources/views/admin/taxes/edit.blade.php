<x-layouts.app title="Edit Tax Rule">
    <x-page-header title="Edit Tax Rule" icon="percent" :subtitle="$taxRule->name">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('taxes.index') }}" icon="chevron-left">Back To Tax</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('taxes.update', $taxRule) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field label="Name" for="name" required :error="$errors->first('name')" class="sm:col-span-2">
                    <x-input id="name" name="name" :value="old('name', $taxRule->name)" required />
                </x-field>
                <x-field label="Country" for="country" required hint="ISO-2 code." :error="$errors->first('country')">
                    <x-input id="country" name="country" :value="old('country', $taxRule->country)" required maxlength="2" class="uppercase" />
                </x-field>
                <x-field label="State / Province" for="state" hint="Blank = whole country." :error="$errors->first('state')">
                    <x-input id="state" name="state" :value="old('state', $taxRule->state)" />
                </x-field>
                <x-field label="Postcode" for="postcode" hint="Blank = whole state. Trailing * matches a prefix." :error="$errors->first('postcode')">
                    <x-input id="postcode" name="postcode" :value="old('postcode', $taxRule->postcode)" />
                </x-field>
                <x-field label="Rate" for="rate" required hint="Percentage, e.g. 7.25." :error="$errors->first('rate')">
                    <x-input id="rate" name="rate" :value="old('rate', $rateInput)" required />
                </x-field>
                <x-field label="Tax Class" for="tax_class" hint="Matches a product's tax class." :error="$errors->first('tax_class')">
                    <x-input id="tax_class" name="tax_class" :value="old('tax_class', $taxRule->tax_class)" />
                </x-field>
                <x-field label="Priority" for="priority" hint="Higher wins when multiple rules match." :error="$errors->first('priority')">
                    <x-input id="priority" name="priority" type="number" min="0" :value="old('priority', $taxRule->priority)" />
                </x-field>
            </div>
            <div class="space-y-4 border-t border-slate-100 pt-5">
                <x-toggle name="applies_to_shipping" :checked="old('applies_to_shipping', $taxRule->applies_to_shipping)" label="Apply To Shipping" description="Also tax the shipping charge, not just line items." />
                <x-toggle name="is_active" :checked="old('is_active', $taxRule->is_active)" label="Active" />
            </div>
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <x-button variant="secondary" href="{{ route('taxes.index') }}">Cancel</x-button>
                <x-button type="submit" icon="check">Save Changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
