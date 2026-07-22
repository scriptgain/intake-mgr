@props(['activities'])
@php
    // Tone -> icon chip colours, resolved here so the markup stays a plain loop.
    $tones = [
        'success' => 'bg-emerald-50 text-emerald-600 ring-emerald-200',
        'info' => 'bg-brand-50 text-brand-600 ring-brand-200',
        'warn' => 'bg-amber-50 text-amber-600 ring-amber-200',
        'danger' => 'bg-rose-50 text-rose-600 ring-rose-200',
        'neutral' => 'bg-slate-100 text-slate-500 ring-slate-200',
    ];
@endphp
@if ($activities->isNotEmpty())
    <ol class="space-y-4">
        @foreach ($activities as $activity)
            <li class="flex items-start gap-3">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-1 ring-inset {{ $tones[$activity->tone] ?? $tones['neutral'] }}">
                    <x-icon :name="$activity->icon" class="w-4 h-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-shop-ink">{{ $activity->message }}</p>
                    <p class="text-xs text-shop-muted">{{ $activity->actor }} &middot; {{ $activity->created_at->format('F j, Y g:i A') }}</p>
                </div>
            </li>
        @endforeach
    </ol>
@else
    <p class="text-sm text-shop-muted">No activity yet.</p>
@endif
