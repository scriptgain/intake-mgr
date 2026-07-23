@props(['title', 'subtitle' => null, 'icon' => null, 'eyebrow' => null, 'back' => null])
{{-- Page header. Four things need to stay instantly separable: where you are
     (eyebrow + title), what this screen is for (subtitle), the one primary
     action, and everything else. So the primary action sits in its own `primary`
     slot on the far right and secondary actions group to its left, rather than
     every button sharing one undifferentiated row. --}}
<div {{ $attributes->merge(['class' => 'pb-5']) }}>
    @if ($back)
        <a href="{{ $back['href'] }}"
            class="mb-3 inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-sm font-medium text-slate-600 shadow-sm ring-1 ring-inset ring-slate-200 transition hover:bg-slate-50 hover:text-slate-900">
            <x-icon name="chevron-left" class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ $back['label'] }}
        </a>
    @endif
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
        <div class="flex min-w-0 items-start gap-3">
            @if ($icon)
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 shadow-sm ring-1 ring-slate-200">
                    <x-icon :name="$icon" class="h-5 w-5" aria-hidden="true" />
                </span>
            @endif
            <div class="min-w-0">
                @if ($eyebrow)<p class="vx-eyebrow mb-1.5">{{ $eyebrow }}</p>@endif
                <h1 class="truncate text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h1>
                @if ($subtitle)<p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>@endif
                @isset($meta)<div class="mt-2.5 flex flex-wrap items-center gap-2">{{ $meta }}</div>@endisset
            </div>
        </div>
        @if (isset($actions) || isset($primary))
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @isset($actions)
                    <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                @endisset
                @isset($primary)
                    @isset($actions)<span class="mx-0.5 hidden h-5 w-px bg-slate-200 sm:block"></span>@endisset
                    {{ $primary }}
                @endisset
            </div>
        @endif
    </div>
</div>
