@props(['items' => []])
@php
    // Items: [label, href, icon, active]. Renders the same vertical stacked-pill
    // menu as the settings left-nav, for any top-nav group (Infrastructure, Backups).
    $items = array_values(array_filter($items));
@endphp
@if (count($items))
    <style>
        .settings-shell{display:grid;grid-template-columns:230px minmax(0,1fr);gap:1.5rem;align-items:start;min-width:0;}
        /* Grid children default to min-width:auto, which lets a wide table or a
           long line push the whole page wider than the viewport (the right edge
           gets cut off). Allowing them to shrink keeps overflow inside the
           scroll region instead of the page body. */
        .settings-shell>*{min-width:0;}
        .settings-aside{position:sticky;top:5rem;}
        @media (max-width:768px){.settings-shell{grid-template-columns:minmax(0,1fr);}.settings-aside{position:static;}}
        .st-menu{display:flex;flex-direction:column;gap:.15rem;background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;padding:.5rem;box-shadow:0 1px 2px rgba(0,0,0,.05);}
        .st-item{display:flex;align-items:center;gap:.6rem;padding:.5rem .6rem;border-radius:.55rem;font-size:.875rem;font-weight:500;color:#475569;text-decoration:none;transition:background .15s,color .15s;}
        .st-item:hover{background:#f1f5f9;color:#0f172a;}
        .st-item.is-active{background:#1e293b;color:#fff;font-weight:600;}
        .st-item svg{width:1.05rem;height:1.05rem;flex:0 0 auto;}
    </style>
    <nav class="st-menu" aria-label="Section menu">
        @foreach ($items as [$label, $href, $icon, $active])
            <a href="{{ $href }}" class="st-item {{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>
                <x-icon :name="$icon" />
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
@endif
