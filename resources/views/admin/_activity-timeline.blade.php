{{-- Shared service-desk timeline. Renders a collection of Activity records the
     same way orders/show renders order events: a hairline runs behind the
     markers so the entries read as one sequence. Expects $activities. --}}
@if ($activities->isEmpty())
    <x-empty-state icon="clock" title="No Activity Recorded"
        description="Status changes, replies, conversions, and staff notes are all logged here in order, so there is one place to see what happened and who did it." />
@else
    <ol class="relative space-y-5 before:absolute before:bottom-4 before:left-4 before:top-4 before:w-px before:bg-slate-200">
        @foreach ($activities as $activity)
            <li class="relative flex gap-3.5">
                <span @class([
                    'relative z-10 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white',
                    'bg-emerald-50 text-emerald-600' => $activity->tone === 'success',
                    'bg-amber-50 text-amber-600' => $activity->tone === 'warn',
                    'bg-rose-50 text-rose-600' => $activity->tone === 'danger',
                    'bg-brand-50 text-brand-600' => $activity->tone === 'info',
                    'bg-slate-100 text-slate-500' => $activity->tone === 'neutral',
                ])>
                    <x-icon :name="$activity->icon" class="h-4 w-4" aria-hidden="true" />
                </span>
                <div class="min-w-0 pt-1">
                    <p class="text-sm text-slate-900">{{ $activity->message }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $activity->actor }} &middot; {{ $activity->created_at?->diffForHumans() }}</p>
                </div>
            </li>
        @endforeach
    </ol>
@endif
