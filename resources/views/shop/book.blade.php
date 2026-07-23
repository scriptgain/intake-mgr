<x-layouts.shop :title="'Book ' . $type->name">
    @php($tz = $type->assignee?->effectiveTimezone())
    @php($oldLabel = old('start') && $tz ? \Carbon\CarbonImmutable::parse(old('start'))->setTimezone($tz)->format('D, M j · g:i A') : '')

    <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">

        @if ($booked = session('booked'))
            {{-- Confirmed --}}
            <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
                <div class="lg:col-span-7">
                    <div class="overflow-hidden rounded-3xl bg-gradient-to-b from-emerald-50/70 to-white p-8 ring-1 ring-shop-line shadow-sm sm:p-10">
                        <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-200">
                            <x-icon name="check-circle" class="h-8 w-8" />
                        </span>
                        <p class="mt-6 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Booking Confirmed</p>
                        <h1 class="mt-1 text-3xl font-bold tracking-tight text-shop-ink sm:text-4xl">You're Booked</h1>
                        <p class="mt-3 text-shop-muted">Your {{ $type->name }} is confirmed for</p>
                        <p class="mt-1 text-xl font-semibold text-shop-ink">{{ $booked['when'] }}</p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-sm font-medium text-shop-ink ring-1 ring-inset ring-shop-line">
                                <x-icon name="check" class="h-4 w-4 text-emerald-600" /> {{ $booked['number'] }}
                            </span>
                            @if ($type->assignee)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-sm font-medium text-shop-ink ring-1 ring-inset ring-shop-line">
                                    <x-icon name="user" class="h-4 w-4 text-brand-600" /> {{ $type->assignee->name }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-6 text-sm text-shop-muted">A calendar invite is on its way. We'll see you then.</p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <x-button href="{{ route('shop.book', $type) }}" variant="secondary">Book Another</x-button>
                            <x-button href="{{ route('shop.home') }}">Back To Home</x-button>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-5">
                    <div class="rounded-3xl bg-white p-6 ring-1 ring-shop-line shadow-sm sm:p-8">
                        <h2 class="text-lg font-bold text-shop-ink">Need To Make A Change?</h2>
                        <p class="mt-1.5 text-sm text-shop-muted">Reschedule to another time or cancel — no phone call needed.</p>
                        @if (! empty($booked['manage_url']))
                            <div class="mt-5 space-y-2.5">
                                <x-button href="{{ $booked['manage_url'] }}" icon="clock" class="w-full justify-center">Reschedule</x-button>
                                <x-button href="{{ $booked['manage_url'] }}" variant="secondary" class="w-full justify-center">Cancel Booking</x-button>
                            </div>
                        @endif
                        <p class="mt-5 border-t border-shop-line pt-4 text-xs text-shop-muted">Keep this page's link handy — it's how you manage this appointment.</p>
                    </div>
                </div>
            </div>

        @elseif (empty($days))
            <div class="mx-auto max-w-xl rounded-3xl bg-white p-8 text-center ring-1 ring-shop-line shadow-sm">
                <span class="mx-auto mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
                    <x-icon name="clock" class="h-7 w-7" />
                </span>
                <h2 class="text-xl font-bold text-shop-ink">No Open Times Right Now</h2>
                <p class="mt-2 text-shop-muted">There's nothing available to book online in the next two weeks. Send us a request and we'll find a time that works.</p>
                <div class="mt-6 flex justify-center">
                    <x-button href="{{ route('shop.request') }}">Send A Request</x-button>
                </div>
            </div>

        @else
            <div x-data="{
                    step: {{ $errors->any() && old('start') ? 2 : 1 }},
                    dayIndex: 0,
                    start: @js(old('start', '')),
                    startLabel: @js($oldLabel),
                    choose(value, label) { this.start = value; this.startLabel = label; },
                    next() { if (this.start) this.step = 2; },
                 }"
                 class="grid gap-6 lg:grid-cols-12 lg:gap-8">

                {{-- ── Left rail: what you're booking + progress ─────────── --}}
                <aside class="lg:col-span-4 xl:col-span-3">
                    <div class="lg:sticky lg:top-24 overflow-hidden rounded-3xl bg-gradient-to-b from-brand-50/80 to-white ring-1 ring-shop-line shadow-sm">
                        <div class="p-6">
                            <a href="{{ route('shop.home') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                                <x-icon name="chevron-left" class="h-4 w-4" /> Home
                            </a>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">Book An Appointment</p>
                            <h1 class="mt-1 text-2xl font-bold tracking-tight text-shop-ink">{{ $type->name }}</h1>
                            @if ($type->description)
                                <p class="mt-3 text-sm leading-relaxed text-shop-muted">{{ $type->description }}</p>
                            @endif

                            <dl class="mt-5 space-y-2.5 text-sm">
                                <div class="flex items-center gap-2.5 text-shop-ink">
                                    <x-icon name="clock" class="h-4 w-4 shrink-0 text-brand-600" /> {{ $type->duration_minutes }} minutes
                                </div>
                                <div class="flex items-center gap-2.5 text-shop-ink">
                                    <x-icon name="percent" class="h-4 w-4 shrink-0 text-brand-600" /> {{ $type->price_formatted }}
                                </div>
                                @if ($type->assignee)
                                    <div class="flex items-center gap-2.5 text-shop-ink">
                                        <x-icon name="user" class="h-4 w-4 shrink-0 text-brand-600" /> {{ $type->assignee->name }}
                                    </div>
                                @endif
                            </dl>
                        </div>

                        {{-- Vertical stepper --}}
                        <ol class="border-t border-shop-line/70 p-6 space-y-4">
                            @foreach (['Choose a time' => 'Pick a day and slot', 'Your details' => 'Tell us who you are'] as $label => $sub)
                                @php($n = $loop->iteration)
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-1 ring-inset transition"
                                          x-bind:class="step > {{ $n }} ? 'bg-brand-600 text-white ring-brand-600'
                                              : (step === {{ $n }} ? 'bg-brand-50 text-brand-700 ring-brand-300' : 'bg-white text-slate-400 ring-slate-200')">
                                        <span x-show="step <= {{ $n }}">{{ $n }}</span>
                                        <template x-if="step > {{ $n }}"><span>&check;</span></template>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold" x-bind:class="step >= {{ $n }} ? 'text-shop-ink' : 'text-slate-400'">{{ $label }}</p>
                                        <p class="text-xs text-shop-muted">{{ $sub }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </aside>

                {{-- ── Right: the active step ────────────────────────────── --}}
                <div class="lg:col-span-8 xl:col-span-9">
                    <form method="POST" action="{{ route('shop.book.store', $type) }}">
                        @csrf
                        <input type="hidden" name="start" x-bind:value="start">

                        {{-- Step 1: choose a time --}}
                        <div x-show="step === 1" x-cloak>
                            <div class="rounded-3xl bg-white ring-1 ring-shop-line shadow-sm">
                                <div class="flex gap-2 overflow-x-auto border-b border-shop-line p-4">
                                    @foreach ($days as $i => $day)
                                        <button type="button" x-on:click="dayIndex = {{ $i }}"
                                                x-bind:class="dayIndex === {{ $i }} ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-transparent bg-white text-shop-muted hover:bg-slate-50'"
                                                class="flex min-w-[4.5rem] shrink-0 flex-col items-center rounded-xl border px-3 py-2 text-center ring-1 ring-inset ring-shop-line transition">
                                            <span class="text-xs font-medium uppercase tracking-wide">{{ \Illuminate\Support\Str::before($day['label'], ',') }}</span>
                                            <span class="text-lg font-bold leading-tight text-shop-ink">{{ \Illuminate\Support\Str::afterLast($day['label'], ' ') }}</span>
                                            <span class="text-[0.65rem] font-medium uppercase text-slate-400">{{ \Illuminate\Support\Str::of($day['label'])->after(' ')->before(' ') }}</span>
                                        </button>
                                    @endforeach
                                </div>

                                <div class="p-5 sm:p-6">
                                    @foreach ($days as $i => $day)
                                        <div x-show="dayIndex === {{ $i }}" x-cloak>
                                            <p class="text-sm font-semibold text-shop-ink">{{ $day['label'] }}</p>
                                            <p class="mt-0.5 text-xs text-shop-muted">Times in {{ $tz }}. Each appointment runs {{ $type->duration_minutes }} minutes.</p>
                                            <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                                                @foreach ($day['slots'] as $slot)
                                                    <button type="button"
                                                            x-on:click="choose(@js($slot['start']), @js($day['label'] . ' · ' . $slot['label']))"
                                                            x-bind:class="start === @js($slot['start']) ? 'border-brand-600 bg-brand-600 text-white' : 'border-shop-line bg-white text-shop-ink hover:border-brand-400 hover:text-brand-700'"
                                                            class="rounded-xl border px-2 py-2.5 text-sm font-semibold transition">
                                                        {{ $slot['label'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-5 flex items-center justify-between gap-3">
                                <p class="text-sm text-shop-muted" x-show="start" x-cloak>
                                    Selected <span class="font-semibold text-shop-ink" x-text="startLabel"></span>
                                </p>
                                <p class="text-sm text-shop-muted" x-show="! start">Pick a time to continue.</p>
                                <x-button type="button" icon="chevron-right" x-on:click="next()" x-bind:disabled="! start"
                                          x-bind:class="! start ? 'pointer-events-none opacity-40' : ''">Continue</x-button>
                            </div>
                        </div>

                        {{-- Step 2: your details --}}
                        <div x-show="step === 2" x-cloak>
                            <div class="rounded-3xl bg-white p-5 ring-1 ring-shop-line shadow-sm sm:p-6">
                                <div class="flex items-center justify-between gap-3 rounded-xl bg-brand-50 px-4 py-3 ring-1 ring-inset ring-brand-100">
                                    <div class="flex items-center gap-2 text-sm text-brand-800">
                                        <x-icon name="clock" class="h-4 w-4 shrink-0" />
                                        <span class="font-medium" x-text="startLabel"></span>
                                    </div>
                                    <button type="button" x-on:click="step = 1" class="text-sm font-medium text-brand-700 hover:text-brand-800">Change</button>
                                </div>

                                @error('start')
                                    <p class="mt-3 text-sm font-medium text-rose-600">{{ $message }}</p>
                                @enderror

                                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                    <x-field label="Your Name" for="name" required :error="$errors->first('name')">
                                        <x-input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" />
                                    </x-field>
                                    <x-field label="Email" for="email" required :error="$errors->first('email')">
                                        <x-input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" />
                                    </x-field>
                                </div>
                                <div class="mt-4">
                                    <x-field label="Phone" for="phone" hint="Optional, helps us reach you." :error="$errors->first('phone')">
                                        <x-input id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" />
                                    </x-field>
                                </div>
                                <div class="mt-4">
                                    <x-field label="Anything We Should Know?" for="notes" :error="$errors->first('notes')">
                                        <textarea id="notes" name="notes" rows="3" maxlength="2000"
                                                  class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('notes') }}</textarea>
                                    </x-field>
                                </div>

                                <details class="mt-4 group" @if ($errors->hasAny(['line1', 'city', 'state', 'postcode'])) open @endif>
                                    <summary class="cursor-pointer text-sm font-medium text-brand-700 hover:text-brand-800">Add A Service Address (Optional)</summary>
                                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                        <div class="sm:col-span-2">
                                            <x-field label="Street Address" for="line1" :error="$errors->first('line1')">
                                                <x-input id="line1" name="line1" value="{{ old('line1') }}" autocomplete="address-line1" />
                                            </x-field>
                                        </div>
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
                                </details>
                            </div>

                            <div class="mt-5 flex items-center justify-between gap-3">
                                <x-button type="button" variant="secondary" icon="chevron-left" x-on:click="step = 1">Back</x-button>
                                <x-button type="submit" icon="check">Confirm Booking</x-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.shop>
