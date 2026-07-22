<x-layouts.app :title="'Availability · ' . $user->name">
    <x-page-header
        eyebrow="Availability"
        :title="$user->name"
        icon="clock"
        subtitle="Set the recurring weekly working hours and any date-specific time off."
        :back="['href' => route('availability.index'), 'label' => 'All Staff']" />

    @php
        // Old input wins after a failed submit, otherwise the saved values.
        $daysValue = old('days', collect($days)->map(fn ($d) => [
            'enabled' => $d['enabled'] ? 1 : 0,
            'start' => $d['start'],
            'end' => $d['end'],
        ])->all());

        $exceptionsValue = old('exceptions', $exceptions);
    @endphp

    <form method="POST" action="{{ route('availability.update', $user) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="min-w-0 space-y-6 lg:col-span-2">
                <x-card title="Weekly Hours" subtitle="Switch on the days this person works and set their hours. Times are in the timezone below.">
                    <div class="space-y-3">
                        @foreach ($days as $weekday => $day)
                            @php $dv = $daysValue[$weekday] ?? ['enabled' => 0, 'start' => '09:00', 'end' => '17:00']; @endphp
                            <div x-data="{ on: {{ ! empty($dv['enabled']) ? 'true' : 'false' }} }"
                                class="rounded-lg p-4 ring-1 ring-slate-200">
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                                    <label class="flex w-40 shrink-0 cursor-pointer select-none items-center gap-3">
                                        <input type="hidden" name="days[{{ $weekday }}][enabled]" :value="on ? 1 : 0">
                                        <button type="button" role="switch" :aria-checked="on.toString()" @click="on = ! on"
                                            :class="on ? 'bg-brand-600' : 'bg-slate-300'"
                                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors">
                                            <span :class="on ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                                        </button>
                                        <span class="text-sm font-medium text-slate-900">{{ $day['label'] }}</span>
                                    </label>

                                    <div class="flex flex-1 flex-wrap items-center gap-2" :class="on ? '' : 'opacity-40'">
                                        <div class="space-y-1">
                                            <label for="day-{{ $weekday }}-start" class="sr-only">{{ $day['label'] }} Start Time</label>
                                            <input type="time" id="day-{{ $weekday }}-start" name="days[{{ $weekday }}][start]" value="{{ $dv['start'] ?? '09:00' }}" :disabled="! on"
                                                class="tabular rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500 disabled:bg-slate-50">
                                        </div>
                                        <span class="text-sm text-slate-400">to</span>
                                        <div class="space-y-1">
                                            <label for="day-{{ $weekday }}-end" class="sr-only">{{ $day['label'] }} End Time</label>
                                            <input type="time" id="day-{{ $weekday }}-end" name="days[{{ $weekday }}][end]" value="{{ $dv['end'] ?? '17:00' }}" :disabled="! on"
                                                class="tabular rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500 disabled:bg-slate-50">
                                        </div>
                                    </div>
                                </div>
                                @if ($errors->has("days.$weekday.start") || $errors->has("days.$weekday.end"))
                                    <p class="mt-2 text-sm text-rose-600">{{ $errors->first("days.$weekday.start") ?: $errors->first("days.$weekday.end") }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>

                <x-card title="Exceptions" subtitle="Override the weekly hours on specific dates: a day off, or special hours.">
                    <div x-data="availabilityExceptions(@js(array_values($exceptionsValue)))" class="space-y-3">
                        <template x-for="(row, index) in rows" :key="index">
                            <div class="rounded-lg p-4 ring-1 ring-slate-200">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                                    <div class="space-y-1.5 sm:col-span-3">
                                        <label class="block text-xs font-medium text-slate-500" :for="`ex-${index}-date`">Date</label>
                                        <input type="date" :id="`ex-${index}-date`" :name="`exceptions[${index}][date]`" x-model="row.date" required
                                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                    </div>
                                    <div class="space-y-1.5 sm:col-span-3">
                                        <label class="block text-xs font-medium text-slate-500" :for="`ex-${index}-avail`">Status</label>
                                        <select :id="`ex-${index}-avail`" :name="`exceptions[${index}][is_available]`" x-model="row.is_available"
                                            class="block w-full appearance-none rounded-lg border-0 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                            <option value="0">Time Off</option>
                                            <option value="1">Special Hours</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1.5 sm:col-span-2" x-show="row.is_available === '1' || row.is_available === 1" x-cloak>
                                        <label class="block text-xs font-medium text-slate-500" :for="`ex-${index}-start`">Start</label>
                                        <input type="time" :id="`ex-${index}-start`" :name="`exceptions[${index}][start]`" x-model="row.start"
                                            class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                    </div>
                                    <div class="space-y-1.5 sm:col-span-2" x-show="row.is_available === '1' || row.is_available === 1" x-cloak>
                                        <label class="block text-xs font-medium text-slate-500" :for="`ex-${index}-end`">End</label>
                                        <input type="time" :id="`ex-${index}-end`" :name="`exceptions[${index}][end]`" x-model="row.end"
                                            class="tabular block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                    </div>
                                    <div class="flex items-end justify-end sm:col-span-2">
                                        <button type="button" @click="removeRow(index)"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-rose-600 ring-1 ring-inset ring-rose-200 transition hover:bg-rose-50"
                                            aria-label="Remove exception">
                                            <x-icon name="trash" class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                    <div class="space-y-1.5 sm:col-span-12">
                                        <label class="block text-xs font-medium text-slate-500" :for="`ex-${index}-reason`">Reason</label>
                                        <input type="text" :id="`ex-${index}-reason`" :name="`exceptions[${index}][reason]`" x-model="row.reason" placeholder="Optional, e.g. Public Holiday"
                                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="! rows.length">
                            <p class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 ring-1 ring-inset ring-slate-100">No exceptions. Add a date to override the weekly hours.</p>
                        </template>

                        <x-button type="button" variant="secondary" icon="plus" @click="addRow()">Add Exception</x-button>
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Timezone" subtitle="The timezone the hours above are interpreted in.">
                    <x-field label="Timezone" for="timezone" required :error="$errors->first('timezone')">
                        <x-select id="timezone" name="timezone">
                            @foreach ($timezones as $tz)
                                <option value="{{ $tz }}" @selected(old('timezone', $user->effectiveTimezone()) === $tz)>{{ $tz }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                </x-card>
            </div>
        </div>

        <div class="sticky bottom-0 z-20 -mx-4 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,0.15)] sm:-mx-6 sm:px-6">
            <x-button variant="secondary" href="{{ route('availability.index') }}">Cancel</x-button>
            <x-button type="submit" icon="check">Save Availability</x-button>
        </div>
    </form>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('availabilityExceptions', (initial) => ({
                rows: (initial || []).map(r => ({
                    date: r.date ?? '',
                    is_available: String(Number(r.is_available) ? 1 : 0),
                    start: r.start ?? '',
                    end: r.end ?? '',
                    reason: r.reason ?? '',
                })),
                addRow() {
                    this.rows.push({ date: '', is_available: '0', start: '', end: '', reason: '' });
                },
                removeRow(index) {
                    this.rows.splice(index, 1);
                },
            }));
        });
    </script>
</x-layouts.app>
