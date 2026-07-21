<x-layouts.shop title="Checkout">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">Checkout</h1>
    </section>

    <div class="section-divider"></div>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <form method="POST" action="{{ route('shop.checkout.place') }}"
              x-data="checkoutForm({
                  quoteUrl: @js(route('shop.checkout.quote')),
                  totals: @js($quote['formatted']),
                  discountError: @js($quote['discount_error']),
                  selectedRateId: {{ $quote['shipping_rate']?->id ?? 'null' }},
                  needsShipping: @js($quote['needs_shipping']),
                  address: {
                      country: @js(old('shipping_address.country', $prefill['country'] ?? 'US')),
                      state: @js(old('shipping_address.state', $prefill['state'] ?? '')),
                      postcode: @js(old('shipping_address.postcode', $prefill['postcode'] ?? '')),
                  },
              })"
              class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            @csrf
            <input type="hidden" name="shipping_rate_id" x-bind:value="selectedRateId ?? ''">
            <input type="hidden" name="billing_same" x-bind:value="billingSame ? 1 : 0">

            <div class="min-w-0 lg:col-span-2 space-y-10">

                {{-- Contact --}}
                <div>
                    <h2 class="text-lg font-semibold text-shop-ink mb-4">Contact</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-field label="Email" for="email" required class="sm:col-span-2">
                            <x-input type="email" id="email" name="email" value="{{ old('email', $customer->email ?? '') }}" required autocomplete="email" />
                        </x-field>
                        <x-field label="Phone" for="phone">
                            <x-input type="tel" id="phone" name="phone" value="{{ old('phone', $customer->phone ?? ($prefill['phone'] ?? '')) }}" autocomplete="tel" />
                        </x-field>
                    </div>
                    @unless ($customer)
                        <div class="mt-3">
                            <x-check-switch name="accepts_marketing" value="1">Email Me About New Arrivals And Offers</x-check-switch>
                        </div>
                    @endunless
                </div>

                <div class="section-divider"></div>

                {{-- Shipping address --}}
                <div>
                    <h2 class="text-lg font-semibold text-shop-ink mb-4">Shipping Address</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-field label="First Name" required>
                            <x-input name="shipping_address[first_name]" value="{{ old('shipping_address.first_name', $prefill['first_name'] ?? '') }}" required autocomplete="given-name" />
                        </x-field>
                        <x-field label="Last Name" required>
                            <x-input name="shipping_address[last_name]" value="{{ old('shipping_address.last_name', $prefill['last_name'] ?? '') }}" required autocomplete="family-name" />
                        </x-field>
                        <x-field label="Company" class="sm:col-span-2">
                            <x-input name="shipping_address[company]" value="{{ old('shipping_address.company', $prefill['company'] ?? '') }}" autocomplete="organization" />
                        </x-field>
                        <x-field label="Address Line 1" required class="sm:col-span-2">
                            <x-input name="shipping_address[line1]" value="{{ old('shipping_address.line1', $prefill['line1'] ?? '') }}" required autocomplete="address-line1" />
                        </x-field>
                        <x-field label="Address Line 2" class="sm:col-span-2">
                            <x-input name="shipping_address[line2]" value="{{ old('shipping_address.line2', $prefill['line2'] ?? '') }}" autocomplete="address-line2" />
                        </x-field>
                        <x-field label="City" required>
                            <x-input name="shipping_address[city]" value="{{ old('shipping_address.city', $prefill['city'] ?? '') }}" required autocomplete="address-level2" />
                        </x-field>
                        <x-field label="State / Province">
                            <x-input name="shipping_address[state]" x-model="address.state" @blur="quote()" autocomplete="address-level1" />
                        </x-field>
                        <x-field label="Postal Code">
                            <x-input name="shipping_address[postcode]" x-model="address.postcode" @blur="quote()" autocomplete="postal-code" />
                        </x-field>
                        <x-field label="Country" required>
                            @php
                                $countries = [
                                    'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
                                    'AU' => 'Australia', 'NZ' => 'New Zealand', 'IE' => 'Ireland',
                                    'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy',
                                    'NL' => 'Netherlands', 'MX' => 'Mexico',
                                ];
                            @endphp
                            <x-select name="shipping_address[country]" x-model="address.country" @change="quote()" required autocomplete="country">
                                @foreach ($countries as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                    </div>
                </div>

                <div class="section-divider"></div>

                {{-- Shipping method --}}
                <div x-show="needsShipping" x-cloak>
                    <h2 class="text-lg font-semibold text-shop-ink mb-4">Shipping Method</h2>

                    <div x-show="!ratesLoaded" class="space-y-2">
                        @forelse ($quote['shipping_rates'] as $rate)
                            <label class="flex items-center justify-between gap-4 rounded-lg ring-1 ring-inset ring-shop-line px-4 py-3 cursor-pointer has-[:checked]:ring-brand-600 has-[:checked]:bg-brand-50 transition">
                                <span class="flex items-center gap-3">
                                    <input type="radio" name="_rate_display" value="{{ $rate->id }}" @checked($quote['shipping_rate']?->id === $rate->id)
                                        @change="selectedRateId = {{ $rate->id }}; quote()" class="text-brand-600 focus:ring-brand-500">
                                    <span>
                                        <span class="block text-sm font-medium text-shop-ink">{{ $rate->name }}</span>
                                        @if ($rate->description)<span class="block text-xs text-shop-muted">{{ $rate->description }}</span>@endif
                                    </span>
                                </span>
                                <span class="text-sm font-medium text-shop-ink tabular">{{ $rate->price_formatted }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-shop-muted">Enter your address to see shipping options.</p>
                        @endforelse
                    </div>

                    <template x-if="ratesLoaded">
                        <div class="space-y-2">
                            <template x-if="rates.length === 0">
                                <p class="text-sm text-shop-muted">No shipping options are available for this address.</p>
                            </template>
                            <template x-for="rate in rates" :key="rate.id">
                                <label class="flex items-center justify-between gap-4 rounded-lg ring-1 ring-inset ring-shop-line px-4 py-3 cursor-pointer has-[:checked]:ring-brand-600 has-[:checked]:bg-brand-50 transition">
                                    <span class="flex items-center gap-3">
                                        <input type="radio" name="_rate_display" x-bind:value="rate.id" x-model.number="selectedRateId" @change="quote()" class="text-brand-600 focus:ring-brand-500">
                                        <span>
                                            <span class="block text-sm font-medium text-shop-ink" x-text="rate.name"></span>
                                            <span class="block text-xs text-shop-muted" x-text="rate.description"></span>
                                        </span>
                                    </span>
                                    <span class="text-sm font-medium text-shop-ink tabular" x-text="rate.price"></span>
                                </label>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="section-divider"></div>

                {{-- Payment --}}
                <div>
                    <h2 class="text-lg font-semibold text-shop-ink mb-4">Payment</h2>
                    <div class="space-y-2">
                        @foreach ($gateways as $slug => $gateway)
                            <label class="flex items-start gap-3 rounded-lg ring-1 ring-inset ring-shop-line px-4 py-3 cursor-pointer has-[:checked]:ring-brand-600 has-[:checked]:bg-brand-50 transition">
                                <input type="radio" name="payment_gateway" value="{{ $slug }}" @checked(old('payment_gateway', array_key_first($gateways)) === $slug) required class="mt-0.5 text-brand-600 focus:ring-brand-500">
                                <span>
                                    <span class="flex items-center gap-2 text-sm font-medium text-shop-ink">
                                        {{ $gateway['label'] }}
                                        @if ($gateway['test_mode'])
                                            {{-- The shopper must be able to tell a store that is not really charging. --}}
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">
                                                <x-icon name="warning" class="w-3 h-3 shrink-0" />
                                                Test Mode
                                            </span>
                                        @endif
                                    </span>
                                    <span class="block text-xs text-shop-muted">{{ $gateway['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @unless ($customer)
                    <div class="section-divider"></div>
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-2">Create An Account</h2>
                        <p class="text-sm text-shop-muted mb-4">Optional. Save Your Details For Faster Checkout Next Time.</p>
                        <x-field label="Password" hint="Leave blank to check out as a guest.">
                            <x-input type="password" name="password" autocomplete="new-password" />
                        </x-field>
                    </div>
                @endunless

                <div class="section-divider"></div>

                {{-- Order note --}}
                <div>
                    <x-field label="Order Notes" for="customer_note">
                        <textarea id="customer_note" name="customer_note" rows="3" placeholder="Notes About Your Order (Optional)"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('customer_note') }}</textarea>
                    </x-field>
                </div>

                {{-- Billing same as shipping (boolean preference: toggle switch, not a plain checkbox) --}}
                <div>
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" x-model="billingSame" class="sr-only peer">
                        <span class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors bg-slate-300 peer-checked:bg-brand-600">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform" x-bind:class="billingSame ? 'translate-x-6' : 'translate-x-1'"></span>
                        </span>
                        <span class="text-sm font-medium text-shop-ink">Billing Address Same As Shipping</span>
                    </label>
                </div>

                <div x-show="!billingSame" x-cloak>
                    <h2 class="text-lg font-semibold text-shop-ink mb-4">Billing Address</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-field label="First Name"><x-input name="billing_address[first_name]" /></x-field>
                        <x-field label="Last Name"><x-input name="billing_address[last_name]" /></x-field>
                        <x-field label="Company" class="sm:col-span-2"><x-input name="billing_address[company]" /></x-field>
                        <x-field label="Address Line 1" class="sm:col-span-2"><x-input name="billing_address[line1]" /></x-field>
                        <x-field label="Address Line 2" class="sm:col-span-2"><x-input name="billing_address[line2]" /></x-field>
                        <x-field label="City"><x-input name="billing_address[city]" /></x-field>
                        <x-field label="State / Province"><x-input name="billing_address[state]" /></x-field>
                        <x-field label="Postal Code"><x-input name="billing_address[postcode]" /></x-field>
                        <x-field label="Country">
                            <x-select name="billing_address[country]">
                                @foreach ($countries as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                    </div>
                </div>

                {{-- Terms: the one acceptable native checkbox (consent), styled properly. --}}
                <div>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="terms" value="1" @if (config('shop.terms_required')) required @endif
                            class="mt-0.5 h-4 w-4 rounded border-slate-200 text-brand-600 focus:ring-2 focus:ring-brand-500">
                        <span class="text-sm text-shop-muted">
                            I Agree To The <a href="#" class="text-brand-700 hover:underline">Terms Of Service</a> And <a href="#" class="text-brand-700 hover:underline">Privacy Policy</a>.
                            @if (config('shop.terms_required'))<span class="text-rose-500">*</span>@endif
                        </span>
                    </label>
                </div>
            </div>

            {{-- Order summary --}}
            <div>
                <x-card title="Order Summary" class="lg:sticky lg:top-24">
                    <ul class="space-y-4 mb-6">
                        @foreach ($cart->items as $item)
                            @php $image = $item->product?->primaryImage(); @endphp
                            <li class="flex items-center gap-3">
                                <span class="shop-media w-14 h-14 rounded-lg shrink-0 relative">
                                    @if ($image)
                                        <img src="{{ $image->url }}" alt="{{ $image->alt_text }}">
                                    @endif
                                    <span class="absolute -top-2 -right-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 rounded-full bg-slate-700 text-white text-[11px] font-semibold px-1">{{ $item->quantity }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-shop-ink truncate">{{ $item->product?->name }}</span>
                                    @if ($item->variant && $item->variant->name !== 'Default')
                                        <span class="block text-xs text-shop-muted">{{ $item->variant->name }}</span>
                                    @endif
                                </span>
                                <span class="text-sm text-shop-ink tabular shrink-0">{{ $item->line_total_formatted }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="space-y-3 text-sm border-t border-shop-line pt-4">
                        <div class="flex justify-between"><dt class="text-shop-muted">Subtotal</dt><dd class="tabular text-shop-ink" x-text="totals.subtotal"></dd></div>
                        <div class="flex justify-between" x-show="totals.discount && totals.discount !== '$0.00'"><dt class="text-shop-muted">Discount</dt><dd class="tabular text-emerald-600" x-text="totals.discount"></dd></div>
                        <div class="flex justify-between"><dt class="text-shop-muted">Shipping</dt><dd class="tabular text-shop-ink" x-text="totals.shipping"></dd></div>
                        <div class="flex justify-between"><dt class="text-shop-muted">Tax</dt><dd class="tabular text-shop-ink" x-text="totals.tax"></dd></div>
                        <div class="pt-3 border-t border-shop-line flex justify-between text-base font-semibold">
                            <dt class="text-shop-ink">Total</dt><dd class="tabular text-shop-ink" x-text="totals.total"></dd>
                        </div>
                    </dl>

                    <template x-if="discountError">
                        <p class="mt-4 text-sm text-rose-600" x-text="discountError"></p>
                    </template>

                    <div class="mt-5"><x-captcha surface="checkout" /></div>

                    <x-slot:footer>
                        <x-button type="submit" size="lg" class="w-full justify-center" x-bind:disabled="quoting">Place Order</x-button>
                    </x-slot:footer>
                </x-card>
            </div>
        </form>
    </section>

</x-layouts.shop>
