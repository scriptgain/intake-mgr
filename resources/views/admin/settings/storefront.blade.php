@php
    $v = fn (string $key, $default = '') => old($key, $settings[$key] ?? $default);
    $checked = fn (string $key, bool $default = false) => old($key, ($settings[$key] ?? ($default ? '1' : '0')) === '1');
@endphp
<x-layouts.app title="Storefront">
    <x-page-header title="Storefront" icon="bag" subtitle="Store identity, catalog behaviour, and checkout policy." />

    <form method="POST" action="{{ route('settings.storefront.update') }}" x-data="{ tab: 'details' }">
        @csrf
        @method('PUT')

        <div class="inline-flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1 mb-6" role="tablist" aria-label="Storefront settings">
            <button type="button" @click="tab = 'details'" :class="tab === 'details' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 rounded-md text-sm font-medium transition">Store Details</button>
            <button type="button" @click="tab = 'catalog'" :class="tab === 'catalog' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 rounded-md text-sm font-medium transition">Catalog</button>
            <button type="button" @click="tab = 'checkout'" :class="tab === 'checkout' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 rounded-md text-sm font-medium transition">Checkout</button>
            <button type="button" @click="tab = 'tax'" :class="tab === 'tax' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 rounded-md text-sm font-medium transition">Tax</button>
            <button type="button" @click="tab = 'appearance'" :class="tab === 'appearance' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 rounded-md text-sm font-medium transition">Appearance</button>
        </div>

        {{-- Store Details --}}
        <div x-show="tab === 'details'" x-cloak>
            <x-card title="Store Details" subtitle="Shown on receipts, emails, and the storefront footer.">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Store Name" for="store_name" required :error="$errors->first('store_name')">
                        <x-input id="store_name" name="store_name" :value="$v('store_name')" required />
                    </x-field>
                    <x-field label="Tagline" for="store_tagline" :error="$errors->first('store_tagline')">
                        <x-input id="store_tagline" name="store_tagline" :value="$v('store_tagline')" />
                    </x-field>
                    <x-field label="Store Email" for="store_email" :error="$errors->first('store_email')">
                        <x-input id="store_email" name="store_email" type="email" :value="$v('store_email')" />
                    </x-field>
                    <x-field label="Store Phone" for="store_phone" :error="$errors->first('store_phone')">
                        <x-input id="store_phone" name="store_phone" :value="$v('store_phone')" />
                    </x-field>
                    <x-field label="Store Address" for="store_address" class="sm:col-span-2" :error="$errors->first('store_address')">
                        <x-input id="store_address" name="store_address" :value="$v('store_address')" />
                    </x-field>
                    <x-field label="Order Number Prefix" for="order_prefix" hint="e.g. SM- produces SM-1042." :error="$errors->first('order_prefix')">
                        <x-input id="order_prefix" name="order_prefix" :value="$v('order_prefix', 'SM-')" />
                    </x-field>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mt-5 border-t border-slate-100 pt-5">
                    <x-field label="Currency Code" for="currency" required :error="$errors->first('currency')">
                        <x-input id="currency" name="currency" :value="$v('currency', 'USD')" required maxlength="3" class="uppercase" />
                    </x-field>
                    <x-field label="Currency Symbol" for="currency_symbol" required :error="$errors->first('currency_symbol')">
                        <x-input id="currency_symbol" name="currency_symbol" :value="$v('currency_symbol', '$')" required />
                    </x-field>
                    <x-field label="Decimal Places" for="currency_decimals" required :error="$errors->first('currency_decimals')">
                        <x-input id="currency_decimals" name="currency_decimals" type="number" min="0" max="4" :value="$v('currency_decimals', '2')" required />
                    </x-field>
                </div>
            </x-card>
        </div>

        {{-- Catalog --}}
        <div x-show="tab === 'catalog'" x-cloak>
            <x-card title="Catalog" subtitle="How products list and track stock.">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Products Per Page" for="products_per_page" required :error="$errors->first('products_per_page')">
                        <x-input id="products_per_page" name="products_per_page" type="number" min="1" max="96" :value="$v('products_per_page', '12')" required />
                    </x-field>
                    <x-field label="Low Stock Threshold" for="low_stock_threshold" required hint="Variants at or below this trigger the dashboard warning." :error="$errors->first('low_stock_threshold')">
                        <x-input id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" :value="$v('low_stock_threshold', '5')" required />
                    </x-field>
                </div>
                <div class="space-y-4 mt-5 border-t border-slate-100 pt-5">
                    <x-toggle name="allow_backorder" :checked="$checked('allow_backorder')" label="Allow Backorders" description="Shoppers can still buy a variant with zero stock." />
                    <x-toggle name="hide_out_of_stock" :checked="$checked('hide_out_of_stock')" label="Hide Out-Of-Stock Products" description="Remove them from storefront listings entirely, instead of showing them as unavailable." />
                </div>
            </x-card>
        </div>

        {{-- Checkout --}}
        <div x-show="tab === 'checkout'" x-cloak>
            <x-card title="Checkout" subtitle="Policy applied when a shopper places an order.">
                <div class="space-y-4">
                    <x-toggle name="guest_checkout" :checked="$checked('guest_checkout', true)" label="Allow Guest Checkout" description="Shoppers can check out without creating an account." />
                    <x-toggle name="terms_required" :checked="$checked('terms_required', true)" label="Require Terms Acceptance" description="Shoppers must accept your terms before placing an order." />
                </div>
            </x-card>
        </div>

        {{-- Tax --}}
        <div x-show="tab === 'tax'" x-cloak>
            <x-card title="Tax" subtitle="How tax is calculated at checkout. Regional rates live under Tax in the main navigation.">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Tax Mode" for="tax_mode" required :error="$errors->first('tax_mode')">
                        <x-select id="tax_mode" name="tax_mode">
                            <option value="exclusive" @selected($v('tax_mode', 'exclusive') === 'exclusive')>Exclusive (added at checkout)</option>
                            <option value="inclusive" @selected($v('tax_mode') === 'inclusive')>Inclusive (already in listed prices)</option>
                        </x-select>
                    </x-field>
                </div>
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <x-toggle name="tax_shipping" :checked="$checked('tax_shipping')" label="Tax Shipping" description="Apply tax to the shipping charge as well as line items." />
                </div>
            </x-card>
        </div>

        {{-- Appearance --}}
        <div x-show="tab === 'appearance'" x-cloak>
            <x-card title="Appearance" subtitle="Layout width for admin and storefront sections.">
                <x-field label="Section Width" for="max_width" required :error="$errors->first('max_width')">
                    <x-select id="max_width" name="max_width">
                        @foreach ($widthOptions as $class => $label)
                            <option value="{{ $class }}" @selected($v('max_width', 'max-w-6xl') === $class)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </x-field>
            </x-card>
        </div>

        <div class="flex justify-end gap-3 pt-6 mt-2">
            <x-button type="submit" icon="check">Save Settings</x-button>
        </div>
    </form>
</x-layouts.app>
