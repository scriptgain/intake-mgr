@props([
    'icon' => 'folder',
    'title' => 'Nothing Here Yet',
    'description' => null,
    'steps' => [],
])
{{-- Empty states teach. An empty screen is the one moment the product has the
     merchant's full attention and nothing competing for it, so it explains what
     the screen is for and what to do next rather than restating the heading in
     the negative ("No Products").

     `steps` renders a short numbered list, used only where reaching a non-empty
     state genuinely is an ordered sequence rather than as decoration.
     Icon sits to the left of the copy, never stacked above centred text. --}}
<div {{ $attributes->merge(['class' => 'flex items-start gap-4 px-6 py-12 text-left']) }}>
    <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-400 ring-1 ring-slate-200">
        <x-icon :name="$icon" class="h-6 w-6" aria-hidden="true" />
    </span>
    <div class="min-w-0">
        <h3 class="text-sm font-semibold text-slate-900">{{ $title }}</h3>
        @if ($description)
            <p class="mt-1 max-w-prose text-sm leading-relaxed text-slate-500">{{ $description }}</p>
        @endif
        @if (count($steps))
            <ol class="mt-4 max-w-prose space-y-2">
                @foreach ($steps as $index => $step)
                    <li class="flex items-start gap-2.5 text-sm text-slate-600">
                        <span class="tabular mt-px inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200">{{ $index + 1 }}</span>
                        <span>{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
        @isset($action)<div class="mt-5 flex flex-wrap items-center gap-2">{{ $action }}</div>@endisset
    </div>
</div>
