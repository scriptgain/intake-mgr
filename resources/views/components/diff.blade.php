@props(['diff', 'leftLabel' => 'Before', 'rightLabel' => 'After'])
{{-- Unified line diff. Rows come from App\Services\DiffService, so this file
     stays markup only. Long lines scroll inside this box and nowhere else, so
     a wide template can never give the page a horizontal scrollbar. --}}
@once
    <style>
        .vx-diff{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;line-height:1.5rem;}
        .vx-diff-row{display:flex;align-items:flex-start;white-space:pre;}
        .vx-diff-num{flex:0 0 3.25rem;text-align:right;padding-right:.75rem;color:#94a3b8;user-select:none;font-variant-numeric:tabular-nums;}
        .vx-diff-mark{flex:0 0 1.25rem;text-align:center;user-select:none;}
        .vx-diff-text{flex:1 1 auto;padding-right:1rem;}
        .vx-diff-add{background:#f0fdf4;color:#166534;}
        .vx-diff-add .vx-diff-num{color:#4ade80;}
        .vx-diff-del{background:#fff1f2;color:#9f1239;}
        .vx-diff-del .vx-diff-num{color:#fb7185;}
        .vx-diff-gap{background:#f8fafc;color:#94a3b8;font-style:italic;}
    </style>
@endonce
<div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-4 py-2 text-xs">
    <span class="font-medium text-slate-500">{{ $leftLabel }} &rarr; {{ $rightLabel }}</span>
    <span class="flex items-center gap-3">
        <span class="font-medium text-emerald-600">+{{ $diff['added'] }}</span>
        <span class="font-medium text-rose-600">-{{ $diff['removed'] }}</span>
    </span>
</div>
@if (! count($diff['rows']))
    <p class="px-4 py-6 text-sm text-slate-500">These two versions are identical.</p>
@else
    <div class="vx-scroll vx-diff max-h-[32rem] overflow-auto">
        @foreach ($diff['rows'] as $row)
            @if ($row['type'] === 'gap')
                <div class="vx-diff-row vx-diff-gap">
                    <span class="vx-diff-num">&nbsp;</span>
                    <span class="vx-diff-mark">&nbsp;</span>
                    <span class="vx-diff-text">{{ $row['text'] ?: '…' }}</span>
                </div>
            @else
                <div class="vx-diff-row {{ $row['type'] === 'add' ? 'vx-diff-add' : ($row['type'] === 'del' ? 'vx-diff-del' : '') }}">
                    <span class="vx-diff-num">{{ $row['type'] === 'add' ? $row['right'] : $row['left'] }}</span>
                    <span class="vx-diff-mark">{{ $row['type'] === 'add' ? '+' : ($row['type'] === 'del' ? '-' : ' ') }}</span>
                    <span class="vx-diff-text">{{ $row['text'] }}</span>
                </div>
            @endif
        @endforeach
    </div>
@endif
