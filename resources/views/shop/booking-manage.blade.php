<x-layouts.shop title="Manage Your Booking">
    @php($tz = $type?->assignee?->effectiveTimezone() ?? config('app.timezone'))
    @php($currentWhen = $workOrder->scheduled_at?->setTimezone($tz)->format('l, F j, Y \a\t g:i A'))
    @php($rescheduleUrl = \Illuminate\Support\Facades\URL::signedRoute('shop.booking.reschedule', $workOrder))
    @php($cancelUrl = \Illuminate\Support\Facades\URL::signedRoute('shop.booking.cancel', $workOrder))

    <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">Manage Your Booking</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-shop-ink">{{ $workOrder->title }}</h1>
        <p class="mt-2 text-shop-muted">Confirmation <span class="font-medium text-shop-ink">{{ $workOrder->number }}</span></p>

        @if ($done = session('rescheduled'))
            <div class="mt-6 flex items-center gap-3 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200">
                <x-icon name="check-circle" class="h-5 w-5 shrink-0" />
                <span>Rescheduled to <span class="font-semibold">{{ $done['when'] }}</span>. A new calendar invite is on its way.</span>
            </div>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-12 lg:gap-8">
            {{-- Reschedule --}}
            <div class="lg:col-span-8">
                <div class="rounded-3xl bg-white ring-1 ring-shop-line shadow-sm">
                    <div class="border-b border-shop-line p-5 sm:p-6">
                        <h2 class="text-lg font-bold text-shop-ink">Reschedule</h2>
                        <p class="mt-1 text-sm text-shop-muted">Currently booked for <span class="font-semibold text-shop-ink">{{ $currentWhen }}</span>. Pick a new time below.</p>
                    </div>

                    @if (empty($days))
                        <div class="p-6 text-sm text-shop-muted">No other open times in the next two weeks. To move this appointment, cancel and contact us.</div>
                    @else
                        <form method="POST" action="{{ $rescheduleUrl }}"
                              x-data="{ dayIndex: 0, start: '', startLabel: '' }">
                            @csrf
                            <input type="hidden" name="start" x-bind:value="start">

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
                                        <p class="mt-0.5 text-xs text-shop-muted">Times in {{ $tz }}.</p>
                                        <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5">
                                            @foreach ($day['slots'] as $slot)
                                                <button type="button"
                                                        x-on:click="start = @js($slot['start']); startLabel = @js($day['label'] . ' · ' . $slot['label'])"
                                                        x-bind:class="start === @js($slot['start']) ? 'border-brand-600 bg-brand-600 text-white' : 'border-shop-line bg-white text-shop-ink hover:border-brand-400 hover:text-brand-700'"
                                                        class="rounded-xl border px-2 py-2.5 text-sm font-semibold transition">
                                                    {{ $slot['label'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                @error('start')
                                    <p class="mt-4 text-sm font-medium text-rose-600">{{ $message }}</p>
                                @enderror

                                <div class="mt-5 flex items-center justify-between gap-3">
                                    <p class="text-sm text-shop-muted" x-show="start" x-cloak>New time <span class="font-semibold text-shop-ink" x-text="startLabel"></span></p>
                                    <x-button type="submit" icon="check" x-bind:disabled="! start"
                                              x-bind:class="! start ? 'pointer-events-none opacity-40' : ''">Confirm New Time</x-button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Current booking + cancel --}}
            <div class="lg:col-span-4">
                <div class="rounded-3xl bg-white p-6 ring-1 ring-shop-line shadow-sm" x-data="{ confirming: false }">
                    <h2 class="text-lg font-bold text-shop-ink">Your Appointment</h2>
                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex items-center gap-2.5 text-shop-ink"><x-icon name="clock" class="h-4 w-4 shrink-0 text-brand-600" /> {{ $currentWhen }}</div>
                        @if ($type)
                            <div class="flex items-center gap-2.5 text-shop-ink"><x-icon name="folder" class="h-4 w-4 shrink-0 text-brand-600" /> {{ $type->name }} ({{ $type->duration_minutes }} min)</div>
                        @endif
                        @if ($workOrder->assignee)
                            <div class="flex items-center gap-2.5 text-shop-ink"><x-icon name="user" class="h-4 w-4 shrink-0 text-brand-600" /> {{ $workOrder->assignee->name }}</div>
                        @endif
                    </dl>

                    <div class="mt-6 border-t border-shop-line pt-5">
                        <div x-show="! confirming">
                            <button type="button" x-on:click="confirming = true"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-rose-600 ring-1 ring-inset ring-rose-200 transition hover:bg-rose-50">
                                <x-icon name="x-circle" class="h-4 w-4" /> Cancel This Booking
                            </button>
                        </div>
                        <div x-show="confirming" x-cloak>
                            <p class="text-sm text-shop-ink">Cancel this appointment? This can't be undone.</p>
                            <div class="mt-3 flex gap-2">
                                <x-button type="button" variant="secondary" size="sm" x-on:click="confirming = false">Keep It</x-button>
                                <form method="POST" action="{{ $cancelUrl }}">
                                    @csrf
                                    <x-button type="submit" variant="danger" size="sm" icon="x-circle">Yes, Cancel</x-button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.shop>
