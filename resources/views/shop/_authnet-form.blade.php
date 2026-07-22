{{--
    Authorize.Net Accept.js card form.

    Included by the generalized pay screen inside its Alpine scope. The card
    number, expiry, CVC and ZIP are entered here and handed straight to
    Accept.js, which tokenizes them in the browser and returns an OPAQUE nonce.
    Only that nonce is posted to our server. The raw card never touches this
    page's form submission, our server, or our logs.

    Expects: $order, $authnet (api_login_id, client_key, sandbox),
             $authnetChargeUrl (signed), $returnUrl, $accent.
--}}

@php
    $anSandbox = (bool) ($authnet['sandbox'] ?? true);
    $anAcceptSrc = $anSandbox
        ? 'https://jstest.authorize.net/v1/Accept.js'
        : 'https://js.authorize.net/v1/Accept.js';
    $anFieldClass = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-shop-line placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<script src="{{ $anAcceptSrc }}" charset="utf-8"></script>

<div x-data="{
        processing: false,
        error: @js($errors->first('payment') ?: ''),
        card: { number: '', expiry: '', cvc: '', zip: '' },
        pay() {
            this.error = '';
            const parsed = window.intakemgrAuthnetParseExpiry(this.card.expiry);
            if (! this.card.number.replace(/\s+/g, '') || ! parsed || ! this.card.cvc.trim()) {
                this.error = 'Please Enter Your Full Card Details.';
                return;
            }
            this.processing = true;
            const self = this;
            window.intakemgrAuthnetPay({
                clientKey: @js($authnet['client_key']),
                apiLoginID: @js($authnet['api_login_id']),
                chargeUrl: @js($authnetChargeUrl),
                returnUrl: @js($returnUrl),
                csrf: @js(csrf_token()),
                card: {
                    number: self.card.number.replace(/\s+/g, ''),
                    month: parsed.month,
                    year: parsed.year,
                    code: self.card.cvc.trim(),
                    zip: self.card.zip.trim(),
                },
                onError(message) { self.processing = false; self.error = message; },
            });
        },
     }"
     style="--an-accent: {{ $accent }};">

    <h2 class="text-lg font-semibold text-shop-ink mb-4">Card Details</h2>

    <template x-if="error">
        <div class="mb-4 flex items-start gap-3 rounded-xl bg-rose-50 px-4 py-3 ring-1 ring-inset ring-rose-200">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-rose-100 text-rose-700 ring-1 ring-rose-200 shrink-0">
                <x-icon name="warning" class="w-4 h-4" />
            </span>
            <p class="text-sm text-rose-800 min-w-0" x-text="error"></p>
        </div>
    </template>

    <div class="space-y-4">
        <div>
            <label for="an-card-number" class="block text-sm font-medium text-shop-ink mb-1.5">Card Number</label>
            <input id="an-card-number" type="text" inputmode="numeric" autocomplete="cc-number"
                   x-model="card.number" placeholder="1234 5678 9012 3456"
                   maxlength="23" class="{{ $anFieldClass }}">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="an-card-expiry" class="block text-sm font-medium text-shop-ink mb-1.5">Expiry (MM/YY)</label>
                <input id="an-card-expiry" type="text" inputmode="numeric" autocomplete="cc-exp"
                       x-model="card.expiry" placeholder="MM/YY"
                       maxlength="7" class="{{ $anFieldClass }}">
            </div>
            <div>
                <label for="an-card-cvc" class="block text-sm font-medium text-shop-ink mb-1.5">CVC</label>
                <input id="an-card-cvc" type="text" inputmode="numeric" autocomplete="cc-csc"
                       x-model="card.cvc" placeholder="123"
                       maxlength="4" class="{{ $anFieldClass }}">
            </div>
        </div>

        <div>
            <label for="an-card-zip" class="block text-sm font-medium text-shop-ink mb-1.5">Billing ZIP</label>
            <input id="an-card-zip" type="text" inputmode="numeric" autocomplete="postal-code"
                   x-model="card.zip" placeholder="12345"
                   maxlength="10" class="{{ $anFieldClass }}">
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <button type="button" x-on:click="pay()" x-bind:disabled="processing"
                class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-semibold text-white shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed"
                style="background-image: linear-gradient(to right, var(--an-accent, #f43f5e), #f97316);">
            <x-icon name="lock" class="w-4 h-4" />
            <span x-show="!processing">Pay {{ $order->total_formatted }}</span>
            <span x-show="processing" x-cloak>Processing</span>
        </button>

        <p class="text-xs text-shop-muted inline-flex items-center gap-1.5">
            <x-icon name="lock" class="w-3.5 h-3.5 shrink-0" />
            Payments Are Processed By Authorize.Net. We Never See Your Card Number.
        </p>
    </div>
</div>

@once
    <script>
        // Split an "MM/YY" or "MM/YYYY" string into the parts Accept.js wants.
        // Returns null when it is not a plausible month + year.
        window.intakemgrAuthnetParseExpiry = function (raw) {
            var m = String(raw || '').match(/^\s*(\d{1,2})\s*[\/\-\s]\s*(\d{2}|\d{4})\s*$/);
            if (! m) { return null; }
            var month = parseInt(m[1], 10);
            if (month < 1 || month > 12) { return null; }
            var year = m[2].length === 2 ? '20' + m[2] : m[2];
            return { month: ('0' + month).slice(-2), year: year };
        };

        // Tokenize with Accept.js, then post ONLY the opaque nonce to the signed
        // charge route. The raw card never leaves the browser via our server.
        window.intakemgrAuthnetPay = function (opts) {
            if (typeof Accept === 'undefined' || ! Accept.dispatchData) {
                opts.onError('The Secure Card Form Is Still Loading. Please Try Again In A Moment.');
                return;
            }

            var secureData = {
                authData: { clientKey: opts.clientKey, apiLoginID: opts.apiLoginID },
                cardData: {
                    cardNumber: opts.card.number,
                    month: opts.card.month,
                    year: opts.card.year,
                    cardCode: opts.card.code,
                    zip: opts.card.zip,
                },
            };

            Accept.dispatchData(secureData, function (response) {
                if (! response || response.messages.resultCode !== 'Ok') {
                    var msg = 'We Could Not Read That Card. Please Check The Details And Try Again.';
                    if (response && response.messages && response.messages.message && response.messages.message.length) {
                        msg = response.messages.message[0].text || msg;
                    }
                    opts.onError(msg);
                    return;
                }

                var body = new URLSearchParams();
                body.append('opaque_data_descriptor', response.opaqueData.dataDescriptor);
                body.append('opaque_data_value', response.opaqueData.dataValue);

                fetch(opts.chargeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': opts.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: body.toString(),
                    credentials: 'same-origin',
                }).then(function (res) {
                    // A server-side redirect response means the charge settled.
                    if (res.redirected) { window.location.assign(res.url); return; }
                    var ct = res.headers.get('content-type') || '';
                    if (ct.indexOf('application/json') === -1) {
                        // Non-JSON success (e.g. a rendered confirmation): follow through.
                        if (res.ok) { window.location.assign(opts.returnUrl); return; }
                        opts.onError('We Could Not Complete That Payment. Please Try Again.');
                        return;
                    }
                    return res.json().then(function (data) {
                        if (res.ok && data && data.ok !== false && ! data.error) {
                            window.location.assign((data && data.redirect) ? data.redirect : opts.returnUrl);
                        } else {
                            opts.onError((data && data.error) ? data.error : 'We Could Not Complete That Payment. Please Try Again.');
                        }
                    });
                }).catch(function () {
                    opts.onError('We Could Not Reach The Server. Please Check Your Connection And Try Again.');
                });
            });
        };
    </script>
@endonce
