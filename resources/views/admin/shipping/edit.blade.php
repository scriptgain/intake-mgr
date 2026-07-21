@php
    $bandHint = fn (string $type) => match ($type) {
        'weight' => 'Grams',
        'price' => 'Dollar amount of the cart subtotal',
        default => null,
    };
@endphp
<x-layouts.app title="Edit Shipping Zone">
    <x-page-header title="Edit Shipping Zone" icon="truck" :subtitle="$zone->name">
        <x-slot:actions>
            <x-badge :color="$zone->is_active ? 'success' : 'neutral'" dot>{{ $zone->is_active ? 'Active' : 'Inactive' }}</x-badge>
            <x-button variant="secondary" href="{{ route('shipping.index') }}" icon="chevron-left">Back To Shipping</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-6">
        <x-card title="Zone Details">
            <form method="POST" action="{{ route('shipping.update', $zone) }}" class="space-y-5">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Zone Name" for="name" required :error="$errors->first('name')" class="sm:col-span-2">
                        <x-input id="name" name="name" :value="old('name', $zone->name)" required />
                    </x-field>
                    <x-field label="Countries" for="countries" required hint="Comma-separated ISO-2 codes, or * for the rest of the world." :error="$errors->first('countries')">
                        <x-input id="countries" name="countries" :value="old('countries', implode(', ', $zone->countries ?? []))" required />
                    </x-field>
                    <x-field label="States / Provinces" for="states" hint="Optional. Blank covers the whole country." :error="$errors->first('states')">
                        <x-input id="states" name="states" :value="old('states', implode(', ', $zone->states ?? []))" />
                    </x-field>
                </div>
                <x-toggle name="is_active" :checked="old('is_active', $zone->is_active)" label="Active" description="Inactive zones are not offered at checkout." />
                <div class="flex justify-end pt-2 border-t border-slate-100">
                    <x-button type="submit" icon="check">Save Zone</x-button>
                </div>
            </form>
        </x-card>

        <x-card title="Rates" subtitle="What this zone charges, in the order they're offered.">
            <x-slot:actions>
                <span x-data @click="$dispatch('open-modal', 'add-rate')" class="inline-flex">
                    <x-button size="sm" icon="plus">Add Rate</x-button>
                </span>
            </x-slot:actions>

            @if ($zone->rates->isEmpty())
                <x-empty-state icon="truck" title="No Rates Yet" description="Add at least one rate so this zone can be offered at checkout." />
            @else
                <div class="divide-y divide-slate-100 -mx-5 sm:-mx-6">
                    @foreach ($zone->rates as $rate)
                        <div class="flex items-center justify-between gap-4 px-5 sm:px-6 py-3.5">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $rate->name }}</p>
                                <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::headline($rate->type) }} &middot; {{ $rate->price_formatted }}@if ($rate->description) &middot; {{ $rate->description }}@endif</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <x-badge :color="$rate->is_active ? 'success' : 'neutral'" dot>{{ $rate->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                <span x-data @click="$dispatch('open-modal', 'edit-rate-{{ $rate->id }}')" class="inline-flex">
                                    <x-icon-button icon="edit" title="Edit Rate" />
                                </span>
                                <x-delete-button :action="route('shipping.rates.destroy', $rate)" name="del-rate-{{ $rate->id }}"
                                    title="Remove Rate?" message="This removes '{{ $rate->name }}' from the zone." />
                            </div>
                        </div>

                        {{-- Edit rate modal --}}
                        <x-modal name="edit-rate-{{ $rate->id }}" title="Edit Rate" icon="truck" maxWidth="max-w-lg">
                            <form id="edit-rate-form-{{ $rate->id }}" method="POST" action="{{ route('shipping.rates.update', $rate) }}"
                                x-data="{ type: '{{ $rate->type }}' }" class="space-y-4">
                                @csrf
                                @method('PUT')
                                <x-field label="Rate Name" required>
                                    <x-input name="name" value="{{ $rate->name }}" required />
                                </x-field>
                                <x-field label="Description">
                                    <x-input name="description" value="{{ $rate->description }}" placeholder="3-5 business days" />
                                </x-field>
                                <div class="grid grid-cols-2 gap-4">
                                    <x-field label="Type" required>
                                        <x-select name="type" x-model="type">
                                            @foreach ($rateTypes as $rt)
                                                <option value="{{ $rt }}">{{ \Illuminate\Support\Str::headline($rt) }}</option>
                                            @endforeach
                                        </x-select>
                                    </x-field>
                                    <x-field label="Price">
                                        <x-input name="price" value="{{ $rate->price_input }}" placeholder="0.00" />
                                    </x-field>
                                </div>
                                <div x-show="type === 'weight' || type === 'price'" x-cloak class="grid grid-cols-2 gap-4">
                                    <x-field label="Min" :hint="$bandHint($rate->type)">
                                        <x-input name="min_value" value="{{ $rate->min_value_input }}" />
                                    </x-field>
                                    <x-field label="Max" :hint="$bandHint($rate->type)">
                                        <x-input name="max_value" value="{{ $rate->max_value_input }}" />
                                    </x-field>
                                </div>
                                <x-field label="Free Above" hint="Cart subtotal above which this rate becomes free. Blank = never.">
                                    <x-input name="free_above" value="{{ $rate->free_above_input }}" placeholder="0.00" />
                                </x-field>
                                <x-toggle name="is_active" :checked="$rate->is_active" label="Active" />
                            </form>
                            <x-slot:footer>
                                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'edit-rate-{{ $rate->id }}')">Cancel</x-button>
                                <x-button variant="primary" size="sm" type="submit" form="edit-rate-form-{{ $rate->id }}" icon="check">Save Rate</x-button>
                            </x-slot:footer>
                        </x-modal>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    {{-- Add rate modal --}}
    <x-modal name="add-rate" title="Add A Rate" icon="truck" maxWidth="max-w-lg">
        <form id="add-rate-form" method="POST" action="{{ route('shipping.rates.store', $zone) }}" x-data="{ type: 'flat' }" class="space-y-4">
            @csrf
            <x-field label="Rate Name" required>
                <x-input name="name" placeholder="Standard Shipping" required />
            </x-field>
            <x-field label="Description">
                <x-input name="description" placeholder="3-5 business days" />
            </x-field>
            <div class="grid grid-cols-2 gap-4">
                <x-field label="Type" required>
                    <x-select name="type" x-model="type">
                        @foreach ($rateTypes as $rt)
                            <option value="{{ $rt }}">{{ \Illuminate\Support\Str::headline($rt) }}</option>
                        @endforeach
                    </x-select>
                </x-field>
                <x-field label="Price">
                    <x-input name="price" placeholder="0.00" />
                </x-field>
            </div>
            <div x-show="type === 'weight' || type === 'price'" x-cloak class="grid grid-cols-2 gap-4">
                <x-field label="Min" hint="Grams for Weight, dollars for Price.">
                    <x-input name="min_value" />
                </x-field>
                <x-field label="Max" hint="Grams for Weight, dollars for Price.">
                    <x-input name="max_value" />
                </x-field>
            </div>
            <x-field label="Free Above" hint="Cart subtotal above which this rate becomes free. Blank = never.">
                <x-input name="free_above" placeholder="0.00" />
            </x-field>
            <x-toggle name="is_active" :checked="true" label="Active" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'add-rate')">Cancel</x-button>
            <x-button variant="primary" size="sm" type="submit" form="add-rate-form" icon="plus">Add Rate</x-button>
        </x-slot:footer>
    </x-modal>
</x-layouts.app>
