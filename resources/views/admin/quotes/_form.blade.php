@php
    // Seed the Alpine line-item repeater from old input (a failed submit) or the
    // quote's saved items, falling back to one empty row.
    $initialItems = old('items');
    if (! is_array($initialItems)) {
        $initialItems = $quote->exists && $quote->items->isNotEmpty()
            ? $quote->items->map(fn ($item) => [
                'service_id' => $item->service_id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => number_format($item->unit_price_cents / 100, 2, '.', ''),
            ])->all()
            : [['service_id' => '', 'name' => '', 'quantity' => 1, 'unit_price' => '']];
    }
    $address = old('address', $quote->address ?? []);
    $validUntil = old('valid_until', $quote->valid_until?->format('Y-m-d'));
    $discountValue = old('discount', $quote->discount_cents ? number_format($quote->discount_cents / 100, 2, '.', '') : '');
    $taxValue = old('tax', $quote->tax_cents ? number_format($quote->tax_cents / 100, 2, '.', '') : '');

    $initialStep = 'details';
    foreach (array_keys($errors->getMessages()) as $k) {
        if (str_starts_with($k, 'items') || in_array($k, ['discount', 'tax'], true)) { $initialStep = 'items'; break; }
        if (str_starts_with($k, 'address') || $k === 'customer_id') { $initialStep = 'customer'; }
    }
@endphp

<form method="POST" action="{{ $quote->exists ? route('quotes.update', $quote) : route('quotes.store') }}"
    class="space-y-6" x-data="{ step: @js($initialStep) }">
    @csrf
    @if ($quote->exists) @method('PUT') @endif

    <x-segmented label="Quote steps">
        <button type="button" role="tab" :aria-selected="(step === 'details').toString()" @click="step = 'details'"
            class="vx-seg-item" :class="step === 'details' && 'is-active'">1 &middot; Details</button>
        <button type="button" role="tab" :aria-selected="(step === 'items').toString()" @click="step = 'items'"
            class="vx-seg-item" :class="step === 'items' && 'is-active'">2 &middot; Line Items</button>
        <button type="button" role="tab" :aria-selected="(step === 'customer').toString()" @click="step = 'customer'"
            class="vx-seg-item" :class="step === 'customer' && 'is-active'">3 &middot; Customer &amp; Address</button>
    </x-segmented>

    {{-- STEP 1: DETAILS --}}
    <div x-show="step === 'details'" class="space-y-4">
        <x-card title="Details" subtitle="What you are proposing and how long it stands.">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-field label="Title" for="title" required :error="$errors->first('title')" class="sm:col-span-2">
                    <x-input id="title" name="title" :value="old('title', $quote->title)" required autofocus placeholder="e.g. Pool Restoration Estimate" />
                </x-field>
                <x-field label="Status" for="status" required :error="$errors->first('status')">
                    <x-select id="status" name="status">
                        @foreach (\App\Models\Quote::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $quote->status) === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                        @endforeach
                    </x-select>
                </x-field>
                <x-field label="Valid Until" for="valid_until" hint="Optional. The date this estimate expires." :error="$errors->first('valid_until')">
                    <input type="date" id="valid_until" name="valid_until" value="{{ $validUntil }}"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                </x-field>
                <x-field label="Message To Customer" for="message" hint="Shown to the customer on the quote." :error="$errors->first('message')" class="sm:col-span-2">
                    <textarea id="message" name="message" rows="4"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('message', $quote->message) }}</textarea>
                </x-field>
            </div>
        </x-card>
        <div class="flex justify-end">
            <x-button type="button" variant="secondary" @click="step = 'items'">Continue To Line Items</x-button>
        </div>
    </div>

    {{-- STEP 2: LINE ITEMS --}}
    <div x-show="step === 'items'" x-cloak class="space-y-4">
        <x-card title="Line Items" subtitle="The services you are quoting. Pick from the catalog or type a free line.">
            <div x-data="workOrderItems(@js($initialItems), @js($serviceOptions))" class="space-y-3">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="rounded-lg p-4 ring-1 ring-slate-200">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                            <div class="space-y-1.5 sm:col-span-4">
                                <label class="block text-xs font-medium text-slate-500" :for="`item-${index}-service`">Service</label>
                                <div class="relative">
                                    <select :id="`item-${index}-service`" :name="`items[${index}][service_id]`" x-model="row.service_id" @change="applyService(row)"
                                        class="block w-full appearance-none rounded-lg border-0 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        <option value="">Free Text</option>
                                        <template x-for="service in services" :key="service.id">
                                            <option :value="service.id" x-text="service.name"></option>
                                        </template>
                                    </select>
                                    <x-icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                </div>
                            </div>
                            <div class="space-y-1.5 sm:col-span-4">
                                <label class="block text-xs font-medium text-slate-500" :for="`item-${index}-name`">Description</label>
                                <input type="text" :id="`item-${index}-name`" :name="`items[${index}][name]`" x-model="row.name" placeholder="What is included"
                                    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            </div>
                            <div class="space-y-1.5 sm:col-span-1">
                                <label class="block text-xs font-medium text-slate-500" :for="`item-${index}-qty`">Qty</label>
                                <input type="number" min="1" :id="`item-${index}-qty`" :name="`items[${index}][quantity]`" x-model="row.quantity"
                                    class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            </div>
                            <div class="space-y-1.5 sm:col-span-2">
                                <label class="block text-xs font-medium text-slate-500" :for="`item-${index}-price`">Unit Price</label>
                                <input type="text" inputmode="decimal" :id="`item-${index}-price`" :name="`items[${index}][unit_price]`" x-model="row.unit_price" placeholder="0.00"
                                    class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            </div>
                            <div class="flex items-end justify-end sm:col-span-1">
                                <button type="button" x-show="rows.length > 1" @click="removeRow(index)"
                                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-rose-600 ring-1 ring-inset ring-rose-200 transition hover:bg-rose-50"
                                    aria-label="Remove line item">
                                    <x-icon name="trash" class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-right text-xs text-slate-500">Line total <span class="font-medium text-slate-700" x-text="lineTotal(row)"></span></p>
                    </div>
                </template>

                <div class="flex items-center justify-between">
                    <x-button type="button" variant="secondary" icon="plus" @click="addRow()">Add Line Item</x-button>
                    <p class="text-sm text-slate-600">Subtotal <span class="tabular font-semibold text-slate-900" x-text="subtotal()"></span></p>
                </div>
            </div>
        </x-card>

        <x-card title="Adjustments" subtitle="Optional discount and tax applied to the subtotal.">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-field label="Discount" for="discount" hint="A flat amount off the subtotal." :error="$errors->first('discount')">
                    <x-input id="discount" name="discount" :value="$discountValue" inputmode="decimal" placeholder="0.00" />
                </x-field>
                <x-field label="Tax" for="tax" hint="A flat tax amount added to the total." :error="$errors->first('tax')">
                    <x-input id="tax" name="tax" :value="$taxValue" inputmode="decimal" placeholder="0.00" />
                </x-field>
            </div>
        </x-card>

        <div class="flex justify-between">
            <x-button type="button" variant="secondary" @click="step = 'details'">Back</x-button>
            <x-button type="button" variant="secondary" @click="step = 'customer'">Continue To Customer</x-button>
        </div>
    </div>

    {{-- STEP 3: CUSTOMER & ADDRESS --}}
    <div x-show="step === 'customer'" x-cloak class="space-y-4">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Customer" subtitle="Who this quote is for.">
                <x-field label="Customer" for="customer_id" :error="$errors->first('customer_id')">
                    <x-select id="customer_id" name="customer_id">
                        <option value="">No Customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_id', $quote->customer_id) === $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>
            </x-card>

            <x-card title="Service Address" subtitle="Where the work would happen.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Address Line 1" for="address_line1" class="sm:col-span-2">
                        <x-input id="address_line1" name="address[line1]" :value="$address['line1'] ?? ''" />
                    </x-field>
                    <x-field label="Address Line 2" for="address_line2" class="sm:col-span-2">
                        <x-input id="address_line2" name="address[line2]" :value="$address['line2'] ?? ''" />
                    </x-field>
                    <x-field label="City" for="address_city">
                        <x-input id="address_city" name="address[city]" :value="$address['city'] ?? ''" />
                    </x-field>
                    <x-field label="State" for="address_state">
                        <x-input id="address_state" name="address[state]" :value="$address['state'] ?? ''" />
                    </x-field>
                    <x-field label="Postcode" for="address_postcode" class="sm:col-span-2">
                        <x-input id="address_postcode" name="address[postcode]" :value="$address['postcode'] ?? ''" />
                    </x-field>
                </div>
            </x-card>
        </div>
        <div class="flex justify-start">
            <x-button type="button" variant="secondary" @click="step = 'items'">Back</x-button>
        </div>
    </div>

    <div class="sticky bottom-0 z-20 -mx-4 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,0.15)] sm:-mx-6 sm:px-6">
        <x-button variant="secondary" href="{{ $quote->exists ? route('quotes.show', $quote) : route('quotes.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $quote->exists ? 'Save Changes' : 'Create Quote' }}</x-button>
    </div>
</form>

{{-- Reuses the same repeater the work-order form registers; quote and work-order
     forms never render on the same page, so a second registration is harmless. --}}
<script>
    document.addEventListener('alpine:init', () => {
        if (window.Alpine && Alpine.data && ! Alpine.__woItemsRegistered) {
            Alpine.__woItemsRegistered = true;
            Alpine.data('workOrderItems', (initial, services) => ({
                rows: initial.length ? initial.map(r => ({
                    service_id: r.service_id ?? '',
                    name: r.name ?? '',
                    quantity: r.quantity ?? 1,
                    unit_price: r.unit_price ?? '',
                })) : [{ service_id: '', name: '', quantity: 1, unit_price: '' }],
                services: services,
                addRow() {
                    this.rows.push({ service_id: '', name: '', quantity: 1, unit_price: '' });
                },
                removeRow(index) {
                    this.rows.splice(index, 1);
                    if (! this.rows.length) this.addRow();
                },
                applyService(row) {
                    const svc = this.services.find(s => String(s.id) === String(row.service_id));
                    if (svc) {
                        if (! row.name) row.name = svc.name;
                        if (! row.unit_price || row.unit_price === '0.00') row.unit_price = svc.price;
                    }
                },
                money(cents) {
                    return '{{ config('shop.currency_symbol', '$') }}' + (cents / 100).toFixed(2);
                },
                lineCents(row) {
                    const qty = parseInt(row.quantity) || 0;
                    const unit = Math.round((parseFloat(String(row.unit_price).replace(/[^0-9.\-]/g, '')) || 0) * 100);
                    return qty * unit;
                },
                lineTotal(row) {
                    return this.money(this.lineCents(row));
                },
                subtotal() {
                    return this.money(this.rows.reduce((sum, row) => sum + this.lineCents(row), 0));
                },
            }));
        }
    });
</script>
