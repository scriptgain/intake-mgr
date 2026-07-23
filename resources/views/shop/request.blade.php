<x-layouts.shop title="Request Service">
    @php($me = auth('customer')->user())

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">

        @if (session('submitted'))
            {{-- Success panel, shown after a submission redirects back here. --}}
            <div class="mx-auto max-w-2xl overflow-hidden rounded-2xl bg-white ring-1 ring-inset ring-emerald-200 shadow-sm">
                <div class="px-6 py-10 sm:px-10 text-center">
                    <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-200">
                        <x-icon name="check-circle" class="w-7 h-7" />
                    </span>
                    <h2 class="mt-5 text-xl font-semibold text-shop-ink">Request Received</h2>
                    <p class="mt-2 text-shop-muted">Thanks. Your request <span class="font-semibold text-shop-ink">{{ session('submitted') }}</span> is in. We have emailed you a confirmation and our team will be in touch shortly.</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <x-button href="{{ route('shop.request') }}" variant="secondary">Submit Another</x-button>
                        @if ($me)
                            <x-button href="{{ route('shop.account.requests') }}">View My Requests</x-button>
                        @else
                            <x-button href="{{ route('shop.home') }}">Back To Home</x-button>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="grid gap-8 lg:grid-cols-5 lg:gap-12">

                {{-- Left: the pitch + what happens next --}}
                <div class="lg:col-span-2">
                    <span class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-700 ring-1 ring-inset ring-brand-200">
                        <x-icon name="bolt" class="w-3.5 h-3.5 shrink-0" /> Fast, No Obligation
                    </span>
                    <h1 class="mt-4 font-display text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">Request Service</h1>
                    <p class="mt-3 text-shop-muted">Tell us what you need and we will get back to you to confirm the details and schedule a visit. It only takes a minute.</p>

                    <div class="mt-8 space-y-5">
                        @foreach ([
                            ['edit', 'Tell Us What You Need', 'Describe the problem and add a few photos if you can.'],
                            ['clock', 'We Confirm & Schedule', 'We review it, reach out, and book a technician at a time that works.'],
                            ['check-circle', 'Track & Pay Online', 'Follow progress and settle your invoice from your account.'],
                        ] as $i => [$icon, $title, $body])
                            <div class="flex items-start gap-3.5">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                                    <x-icon :name="$icon" class="w-5 h-5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-shop-ink">{{ $title }}</p>
                                    <p class="mt-0.5 text-sm leading-relaxed text-shop-muted">{{ $body }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8 flex items-center gap-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-inset ring-shop-line">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 ring-1 ring-inset ring-shop-line">
                            <x-icon name="shield" class="w-5 h-5" />
                        </span>
                        <p class="text-sm text-shop-muted">Vetted, insured technicians. Your details are only used to help with your request.</p>
                    </div>
                </div>

                {{-- Right: the boxed form (header / body / footer) --}}
                <div class="lg:col-span-3">
                    <form method="POST" action="{{ route('shop.request.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="overflow-hidden rounded-2xl bg-white border border-slate-200 shadow-sm">

                            {{-- Panel header --}}
                            <div class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-6 py-5 sm:px-8">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                                    <x-icon name="edit" class="w-5 h-5" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-base font-semibold text-shop-ink">Service Request Details</h2>
                                    <p class="text-sm text-shop-muted">The more you tell us, the faster we can help.</p>
                                </div>
                            </div>

                            {{-- Panel body --}}
                            <div class="space-y-6 px-6 py-6 sm:px-8">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <x-field label="Your Name" for="name" required :error="$errors->first('name')">
                                        <x-input id="name" name="name" value="{{ old('name', $me?->name) }}" required autocomplete="name" />
                                    </x-field>
                                    <x-field label="Email" for="email" required :error="$errors->first('email')">
                                        <x-input type="email" id="email" name="email" value="{{ old('email', $me?->email) }}" required autocomplete="email" />
                                    </x-field>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <x-field label="Phone" for="phone" hint="Optional, but helps us reach you faster." :error="$errors->first('phone')">
                                        <x-input id="phone" name="phone" value="{{ old('phone', $me?->phone) }}" autocomplete="tel" />
                                    </x-field>
                                    @if ($services->isNotEmpty())
                                        <x-field label="Service Needed" for="service_id" hint="Optional." :error="$errors->first('service_id')">
                                            <select id="service_id" name="service_id"
                                                class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                                <option value="">Not Sure / Other</option>
                                                @foreach ($services as $service)
                                                    <option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>
                                                @endforeach
                                            </select>
                                        </x-field>
                                    @endif
                                </div>

                                <x-field label="Subject" for="subject" required :error="$errors->first('subject')">
                                    <x-input id="subject" name="subject" value="{{ old('subject') }}" required maxlength="255" placeholder="e.g. Pool pump is leaking" />
                                </x-field>

                                <x-field label="Describe What You Need" for="description" :error="$errors->first('description')">
                                    <textarea id="description" name="description" rows="5" maxlength="5000"
                                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                                        placeholder="Tell us what is going on, when you noticed it, and anything else that helps.">{{ old('description') }}</textarea>
                                </x-field>

                                {{-- Service address --}}
                                <fieldset class="space-y-4">
                                    <legend class="text-sm font-semibold text-shop-ink">Service Address <span class="font-normal text-shop-muted">(Optional)</span></legend>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <x-field label="Street Address" for="line1" :error="$errors->first('line1')">
                                            <x-input id="line1" name="line1" value="{{ old('line1') }}" autocomplete="address-line1" />
                                        </x-field>
                                        <x-field label="Apt / Unit" for="line2" :error="$errors->first('line2')">
                                            <x-input id="line2" name="line2" value="{{ old('line2') }}" autocomplete="address-line2" />
                                        </x-field>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <x-field label="City" for="city" :error="$errors->first('city')">
                                            <x-input id="city" name="city" value="{{ old('city') }}" autocomplete="address-level2" />
                                        </x-field>
                                        <x-field label="State" for="state" :error="$errors->first('state')">
                                            <x-input id="state" name="state" value="{{ old('state') }}" autocomplete="address-level1" />
                                        </x-field>
                                        <x-field label="ZIP / Postcode" for="postcode" :error="$errors->first('postcode')">
                                            <x-input id="postcode" name="postcode" value="{{ old('postcode') }}" autocomplete="postal-code" />
                                        </x-field>
                                    </div>
                                </fieldset>

                                {{-- Photo uploads --}}
                                <x-field label="Add Photos" for="attachments"
                                         hint="Optional. Images up to 5 MB each, up to 8 photos."
                                         :error="$errors->first('attachments') ?: $errors->first('attachments.0')">
                                    <input type="file" id="attachments" name="attachments[]" accept="image/*" multiple
                                        class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                                </x-field>

                                <x-captcha surface="service_request" />
                            </div>

                            {{-- Panel footer --}}
                            <div class="flex flex-col-reverse items-stretch gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                                @unless ($me)
                                    <span class="text-sm text-shop-muted">Have an account? <a href="{{ route('shop.account.login') }}" class="font-medium text-brand-700 hover:text-brand-800">Sign in</a> to track it.</span>
                                @else
                                    <span class="text-sm text-shop-muted">Signed in as {{ $me->name }}. This request will appear in your account.</span>
                                @endunless
                                <x-button type="submit" size="lg" icon="check" class="justify-center sm:w-auto">Submit Request</x-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </section>

</x-layouts.shop>
