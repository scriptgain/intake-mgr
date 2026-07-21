<x-layouts.app title="Payments">
    @php
        $v = fn (string $key, $default = '') => old($key, $settings[$key] ?? $default);
        $checked = fn (string $key, bool $default = false) => old($key, ($settings[$key] ?? ($default ? '1' : '0')) === '1');
    @endphp

    <x-page-header title="Payments" icon="credit-card" subtitle="Card processing, offline payment, and order email.">
        <x-slot:meta>
            @if ($state === 'ready_live')
                <x-badge color="success" dot>Live Mode</x-badge>
            @elseif ($state === 'ready_test')
                <x-badge color="warn" dot>Test Mode</x-badge>
            @elseif ($state === 'disabled')
                <x-badge color="neutral" dot>Card Payments Off</x-badge>
            @else
                <x-badge color="neutral" dot>Not Configured</x-badge>
            @endif
        </x-slot:meta>
    </x-page-header>

    {{-- State banner. The single most important thing on this screen is whether
         this store is currently taking real money, so it is stated in a full
         width band rather than implied by a small badge. --}}
    @if ($state === 'ready_test')
        <div class="mb-6 flex items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-inset ring-amber-200">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 ring-1 ring-amber-200">
                <x-icon name="warning" class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-900">Storefront Is In Test Mode</p>
                <p class="text-sm text-amber-800">Checkout accepts Stripe test cards only and takes no real money. Shoppers see a test-mode notice on the payment page. Switch to Live below once your live keys are saved.</p>
            </div>
        </div>
    @elseif ($state === 'ready_live')
        <div class="mb-6 flex items-start gap-3 rounded-xl bg-emerald-50 px-4 py-3 ring-1 ring-inset ring-emerald-200">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200">
                <x-icon name="check-circle" class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-emerald-900">Taking Live Card Payments</p>
                <p class="text-sm text-emerald-800">Real cards are charged at checkout and funds settle to your own Stripe account.</p>
            </div>
        </div>
    @else
        <div class="mb-6 flex items-start gap-3 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-inset ring-slate-200">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 ring-1 ring-slate-200">
                <x-icon name="lock" class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Card Payments Are Off</p>
                <p class="text-sm text-slate-600">
                    @if ($state === 'not_configured')
                        Add a publishable key and a secret key for {{ $mode }} mode, then switch card payments on. Until then checkout offers manual payment only.
                    @else
                        Keys are saved but the gateway is switched off. Checkout offers manual payment only.
                    @endif
                </p>
            </div>
        </div>
    @endif

    @if ($isEnabled && ! $webhooksConfigured)
        <div class="mb-6">
        <x-alert type="warn" title="No Webhook Secret Saved">
            Payments will still work, but only while the shopper's browser completes the redirect. Without a webhook,
            a customer who closes the tab during a 3D Secure check leaves an order stuck as pending even though their
            card was charged. Add the signing secret for {{ $mode }} mode below.
        </x-alert>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.payments.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-card title="Card Payments" subtitle="Stripe. Funds settle directly into your own Stripe account.">
            <div class="space-y-5">
                <x-toggle name="stripe_enabled" :checked="$checked('stripe_enabled')" label="Accept Cards At Checkout"
                          description="Stays off until both a publishable key and a secret key are saved for the selected mode." />

                <x-field label="Mode" for="stripe_mode" required :error="$errors->first('stripe_mode')"
                         hint="Test mode uses your test keys and charges nothing. Each mode keeps its own keys, so switching back and forth is safe.">
                    <x-select id="stripe_mode" name="stripe_mode">
                        <option value="test" @selected($v('stripe_mode', 'test') === 'test')>Test</option>
                        <option value="live" @selected($v('stripe_mode') === 'live')>Live</option>
                    </x-select>
                </x-field>

                {{-- Test and live credentials on separate tabs, so a live key is
                     never one careless paste away from the test box. --}}
                <div x-data="{ keyTab: @js($mode) }">
                    <div class="flex items-center gap-1 border-b border-slate-200">
                        <button type="button" x-on:click="keyTab = 'test'"
                                x-bind:class="keyTab === 'test' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                                class="inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition">
                            <x-icon name="warning" class="h-4 w-4 shrink-0" />
                            Test Keys
                            @if ($stored['test']['secret_key'])<x-badge color="success">Set</x-badge>@endif
                        </button>
                        <button type="button" x-on:click="keyTab = 'live'"
                                x-bind:class="keyTab === 'live' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                                class="inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition">
                            <x-icon name="bolt" class="h-4 w-4 shrink-0" />
                            Live Keys
                            @if ($stored['live']['secret_key'])<x-badge color="success">Set</x-badge>@endif
                        </button>
                    </div>

                    @foreach (['test', 'live'] as $keyMode)
                        <div x-show="keyTab === @js($keyMode)" x-cloak class="pt-5">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <x-field label="Publishable Key" for="{{ $keyMode }}_publishable_key"
                                         :error="$errors->first($keyMode.'_publishable_key')"
                                         hint="Public by design. Safe to appear in page source.">
                                    <x-input id="{{ $keyMode }}_publishable_key" name="{{ $keyMode }}_publishable_key"
                                             :value="old($keyMode.'_publishable_key', $publishableKeys[$keyMode])"
                                             placeholder="pk_{{ $keyMode }}_..." autocomplete="off" />
                                </x-field>

                                <x-field for="{{ $keyMode }}_secret_key" :error="$errors->first($keyMode.'_secret_key')"
                                         hint="Never shown again once saved.">
                                    <x-slot:label>
                                        Secret Key
                                        @if ($stored[$keyMode]['secret_key'])<x-badge color="success" class="ml-2 align-middle">Configured</x-badge>@endif
                                    </x-slot:label>
                                    <x-input id="{{ $keyMode }}_secret_key" name="{{ $keyMode }}_secret_key" type="password" autocomplete="off"
                                             :placeholder="$stored[$keyMode]['secret_key'] ? 'Leave blank to keep the current key' : 'sk_'.$keyMode.'_...'" />
                                </x-field>

                                <x-field for="{{ $keyMode }}_webhook_secret" :error="$errors->first($keyMode.'_webhook_secret')"
                                         class="sm:col-span-2"
                                         hint="From the endpoint you create in Stripe. Each endpoint has its own secret.">
                                    <x-slot:label>
                                        Webhook Signing Secret
                                        @if ($stored[$keyMode]['webhook_secret'])<x-badge color="success" class="ml-2 align-middle">Configured</x-badge>@endif
                                    </x-slot:label>
                                    <x-input id="{{ $keyMode }}_webhook_secret" name="{{ $keyMode }}_webhook_secret" type="password" autocomplete="off"
                                             :placeholder="$stored[$keyMode]['webhook_secret'] ? 'Leave blank to keep the current secret' : 'whsec_...'" />
                                </x-field>
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-field label="Statement Descriptor" for="stripe_statement_descriptor"
                         hint="Up to 22 characters, appended to what shows on the customer's card statement."
                         :error="$errors->first('stripe_statement_descriptor')">
                    <x-input id="stripe_statement_descriptor" name="stripe_statement_descriptor"
                             :value="$v('stripe_statement_descriptor')" maxlength="22" placeholder="{{ config('shop.store_name') }}" />
                </x-field>

                <x-alert type="info" title="Your Webhook Endpoint">
                    <p>In the Stripe dashboard, add an endpoint pointing at:</p>
                    <p class="mt-2 break-all rounded-lg bg-white/70 px-3 py-2 font-mono text-xs text-slate-700 ring-1 ring-inset ring-slate-200">{{ $webhookUrl }}</p>
                    <p class="mt-2">Subscribe it to <span class="font-medium">payment_intent.succeeded</span>, <span class="font-medium">payment_intent.payment_failed</span>, <span class="font-medium">payment_intent.canceled</span>, <span class="font-medium">charge.refunded</span> and <span class="font-medium">charge.dispute.created</span>, then paste its signing secret above. Create a separate endpoint for test and for live: their signing secrets differ.</p>
                </x-alert>

                <x-alert type="info">
                    Secrets are write-only and never render back into this form. Leaving a secret field blank keeps the
                    value already saved; type a new one only to replace it.
                </x-alert>
            </div>
        </x-card>

        <x-card title="Manual / Offline Payment" subtitle="Always available as a fallback, so orders can be placed before any gateway is wired up.">
            <div class="space-y-5">
                <x-toggle name="manual_enabled" :checked="$checked('manual_enabled', true)" label="Enabled" />
                <x-field label="Payment Instructions" for="manual_instructions" hint="Shown to the shopper at checkout and on the order confirmation." :error="$errors->first('manual_instructions')">
                    <textarea id="manual_instructions" name="manual_instructions" rows="3" maxlength="2000"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ $v('manual_instructions') }}</textarea>
                </x-field>
            </div>
        </x-card>

        <x-card title="Order Email">
            <div class="space-y-5">
                <x-toggle name="order_email_customer" :checked="$checked('order_email_customer', true)"
                          label="Send Confirmation To The Customer"
                          description="Order contents, totals, and shipping details." />
                <x-toggle name="order_email_merchant" :checked="$checked('order_email_merchant', true)"
                          label="Notify The Merchant Of New Orders" />
                <x-field label="Notification Email" for="order_notify_email"
                         hint="Where new-order notifications go. Falls back to the store email in Storefront settings."
                         :error="$errors->first('order_notify_email')">
                    <x-input id="order_notify_email" name="order_notify_email" type="email" :value="$v('order_notify_email')" placeholder="orders@example.com" />
                </x-field>
            </div>
        </x-card>

        <x-card title="Default Gateway" subtitle="Pre-selected at checkout when more than one is available.">
            <x-field label="Gateway" for="default_gateway" required :error="$errors->first('default_gateway')">
                <x-select id="default_gateway" name="default_gateway">
                    @foreach ($gateways as $key => $label)
                        <option value="{{ $key }}" @selected($v('default_gateway', 'manual') === $key)>{{ $label }}</option>
                    @endforeach
                </x-select>
            </x-field>
        </x-card>

        <div class="flex justify-end gap-3">
            <x-button type="submit" icon="check">Save Settings</x-button>
        </div>
    </form>

    {{-- Separate form: nesting it inside the settings form would submit both. --}}
    <form method="POST" action="{{ route('settings.payments.test') }}" class="mt-4 flex items-center justify-between gap-4 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-inset ring-slate-200">
        @csrf
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-900">Test The Connection</p>
            <p class="text-sm text-slate-600">Calls Stripe with the saved {{ $mode }} secret key and reports which account answers.</p>
        </div>
        <x-button type="submit" variant="secondary" size="sm" icon="bolt">Test Stripe Keys</x-button>
    </form>
</x-layouts.app>
