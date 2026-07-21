@props(['label' => 'Filters'])
{{-- A data surface: the filter toolbar is attached to the top edge of the same
     card that holds the table, instead of floating above it as a third stacked
     row. Saves roughly 80px of vertical before the first row of data and makes
     it obvious the filters act on this table.

     Slots: `toolbar` (segmented tabs), `search` (search/filter form),
     `bulk` (selection action bar), default slot (the table). --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200']) }}>
    @if (isset($toolbar) || isset($search))
        <div class="vx-toolbar">
            <div class="min-w-0">{{ $toolbar ?? '' }}</div>
            @isset($search)
                <div class="flex min-w-0 flex-wrap items-center gap-2">{{ $search }}</div>
            @endisset
        </div>
    @endif
    {{ $bulk ?? '' }}
    {{ $slot }}
</div>
