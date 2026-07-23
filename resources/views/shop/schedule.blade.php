@php($initial = $types->firstWhere('slug', old('type')) ?? $types->first())
<x-layouts.shop title="Schedule Your Visit">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:py-14"
         x-data="{
            typeId: {{ $initial->id }},
            typeSlug: @js($initial->slug),
            start: @js(old('start', '')),
            startLabel: @js(old('start') ? 'Your selected time' : ''),
         }">

        <h1 class="text-2xl font-semibold text-shop-ink">Schedule Your Visit</h1>
        <p class="mt-1 text-shop-muted">Pick a time for request <span class="font-medium text-shop-ink">{{ $serviceRequest->number }}</span> &mdash; {{ $serviceRequest->subject }}.</p>

        {{-- Choose the appointment type --}}
        <div class="mt-6">
            <p class="text-sm font-semibold text-shop-ink">What Kind Of Appointment?</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($types as $t)
                    <button type="button"
                            x-on:click="typeId = {{ $t->id }}; typeSlug = @js($t->slug); start = ''; startLabel = ''"
                            x-bind:class="typeId === {{ $t->id }} ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-shop-ink ring-slate-300 hover:ring-brand-400'"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition">
                        {{ $t->name }}
                        <span class="opacity-70">· {{ $t->duration_minutes }}m</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Slot panels, one per type --}}
        @foreach ($types as $t)
            <div x-show="typeId === {{ $t->id }}" x-cloak class="mt-6 rounded-2xl bg-white p-5 ring-1 ring-slate-200 sm:p-6">
                @php($days = $slotsByType[$t->id] ?? [])
                @if (empty($days))
                    <x-alert type="info" title="No Open Times Right Now">
                        No available {{ $t->name }} appointments in the next two weeks. Try another type, or we'll reach out to arrange one.
                    </x-alert>
                @else
                    <h2 class="text-base font-semibold text-shop-ink">Pick A Time</h2>
                    <p class="mt-0.5 text-sm text-shop-muted">Times shown in {{ $t->assignee->effectiveTimezone() }}.</p>
                    <div class="mt-4 space-y-5">
                        @foreach ($days as $day)
                            <div>
                                <p class="text-sm font-semibold text-shop-ink">{{ $day['label'] }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($day['slots'] as $slot)
                                        <button type="button"
                                                x-on:click="start = @js($slot['start']); startLabel = @js($day['label'] . ' · ' . $slot['label'])"
                                                x-bind:class="start === @js($slot['start'])
                                                    ? 'bg-brand-600 text-white ring-brand-600'
                                                    : 'bg-white text-shop-ink ring-slate-300 hover:ring-brand-400'"
                                                class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition">
                                            {{ $slot['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        @error('start')
            <p class="mt-3 text-sm font-medium text-rose-600">{{ $message }}</p>
        @enderror

        {{-- Confirm --}}
        <form method="POST" action="{{ route('shop.schedule.store', $serviceRequest) }}" class="mt-6 rounded-2xl bg-white p-5 ring-1 ring-slate-200 sm:p-6"
              x-show="start" x-cloak>
            @csrf
            <input type="hidden" name="type" x-bind:value="typeSlug">
            <input type="hidden" name="start" x-bind:value="start">

            <div class="flex items-center gap-2 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-800 ring-1 ring-inset ring-brand-100">
                <x-icon name="clock" class="h-4 w-4 shrink-0" />
                <span x-text="startLabel"></span>
            </div>

            <div class="mt-4">
                <x-field label="Anything We Should Know?" for="notes" :error="$errors->first('notes')">
                    <textarea id="notes" name="notes" rows="3" maxlength="2000"
                              class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('notes') }}</textarea>
                </x-field>
            </div>

            <div class="mt-6">
                <x-button type="submit" icon="check">Confirm Appointment</x-button>
            </div>
        </form>
    </div>
</x-layouts.shop>
