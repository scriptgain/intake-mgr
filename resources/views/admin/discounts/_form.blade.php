@php $isEdit = $discount->exists; @endphp
<form method="POST" action="{{ $isEdit ? route('discounts.update', $discount) : route('discounts.store') }}" class="space-y-6"
    x-data="{ type: '{{ old('type', $discount->type ?? 'percentage') }}', appliesTo: '{{ old('applies_to', $discount->applies_to ?? 'all') }}' }">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="min-w-0 lg:col-span-2 space-y-6">
            <x-card title="Discount">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Code" for="code" required hint="Shoppers type this at checkout." :error="$errors->first('code')">
                        <x-input id="code" name="code" :value="old('code', $discount->code)" required class="font-mono uppercase" />
                    </x-field>
                    <x-field label="Title" for="title" hint="Internal label, not shown to shoppers." :error="$errors->first('title')">
                        <x-input id="title" name="title" :value="old('title', $discount->title)" />
                    </x-field>

                    <x-field label="Type" for="type" required>
                        <x-select id="type" name="type" x-model="type">
                            @foreach (\App\Models\Discount::TYPES as $type)
                                <option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <div x-show="type !== 'free_shipping'" x-cloak>
                        <x-field :label="null" for="value" :error="$errors->first('value')">
                            <label class="block text-sm font-medium text-slate-700 mb-1.5" x-text="type === 'percentage' ? 'Percent Off' : 'Amount Off'"></label>
                            <x-input id="value" name="value" :value="old('value', $valueInput)" :placeholder="'0.00'" />
                        </x-field>
                    </div>

                    <x-field label="Applies To" for="applies_to" required>
                        <x-select id="applies_to" name="applies_to" x-model="appliesTo">
                            <option value="all">All Products</option>
                            <option value="collections">Specific Collections</option>
                            <option value="products">Specific Products</option>
                        </x-select>
                    </x-field>
                    <x-field label="Minimum Subtotal" for="min_subtotal" hint="Blank = no minimum." :error="$errors->first('min_subtotal')">
                        <x-input id="min_subtotal" name="min_subtotal" :value="old('min_subtotal', $minSubtotalInput)" placeholder="0.00" />
                    </x-field>
                </div>
            </x-card>

            <div x-show="appliesTo === 'collections'" x-cloak>
                <x-card title="Collections" subtitle="Only items in these collections qualify.">
                    @if ($collections->isEmpty())
                        <p class="text-sm text-slate-400">No collections yet.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            @foreach ($collections as $collection)
                                <div class="rounded-lg ring-1 ring-slate-200 px-3 py-2.5 hover:bg-slate-50 transition">
                                    <x-check-switch name="target_ids[]" :value="$collection->id"
                                        :checked="in_array($collection->id, old('target_ids', $selectedTargets))">
                                        <span class="truncate">{{ $collection->name }}</span>
                                    </x-check-switch>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            </div>

            <div x-show="appliesTo === 'products'" x-cloak>
                <x-card title="Products" subtitle="Only these products qualify.">
                    @if ($products->isEmpty())
                        <p class="text-sm text-slate-400">No products yet.</p>
                    @else
                        <div class="max-h-96 overflow-y-auto vx-scroll grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            @foreach ($products as $product)
                                <div class="rounded-lg ring-1 ring-slate-200 px-3 py-2.5 hover:bg-slate-50 transition">
                                    <x-check-switch name="target_ids[]" :value="$product->id"
                                        :checked="in_array($product->id, old('target_ids', $selectedTargets))">
                                        <span class="truncate">{{ $product->name }}</span>
                                    </x-check-switch>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            </div>

            <x-card title="Usage Limits">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Total Usage Limit" for="usage_limit" hint="Blank = unlimited." :error="$errors->first('usage_limit')">
                        <x-input id="usage_limit" name="usage_limit" type="number" min="1" :value="old('usage_limit', $discount->usage_limit)" />
                    </x-field>
                    <x-field label="Limit Per Customer" for="usage_limit_per_customer" hint="Blank = unlimited." :error="$errors->first('usage_limit_per_customer')">
                        <x-input id="usage_limit_per_customer" name="usage_limit_per_customer" type="number" min="1" :value="old('usage_limit_per_customer', $discount->usage_limit_per_customer)" />
                    </x-field>
                </div>
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <x-toggle name="once_per_customer" :checked="old('once_per_customer', $discount->once_per_customer ?? false)"
                        label="Once Per Customer" description="Overrides the per-customer limit above with a hard cap of one." />
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Active Dates">
                <div class="space-y-5">
                    <x-field label="Starts At" for="starts_at" hint="Blank = starts immediately." :error="$errors->first('starts_at')">
                        <x-input id="starts_at" name="starts_at" type="datetime-local" :value="old('starts_at', $discount->starts_at?->format('Y-m-d\TH:i'))" />
                    </x-field>
                    <x-field label="Ends At" for="ends_at" hint="Blank = never expires." :error="$errors->first('ends_at')">
                        <x-input id="ends_at" name="ends_at" type="datetime-local" :value="old('ends_at', $discount->ends_at?->format('Y-m-d\TH:i'))" />
                    </x-field>
                </div>
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <x-toggle name="is_active" :checked="old('is_active', $discount->is_active ?? true)"
                        label="Active" description="Inactive codes cannot be redeemed." />
                </div>
            </x-card>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-6">
        <x-button variant="secondary" href="{{ route('discounts.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $isEdit ? 'Save Changes' : 'Create Discount' }}</x-button>
    </div>
</form>
