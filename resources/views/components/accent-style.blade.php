{{-- Theme custom properties, declared ahead of the CSS the in-browser Tailwind
     build injects, so the brand ramp is right from first paint.
     Built by App\View\Components\AccentStyle. --}}
@if ($css !== '')
    <style>{!! $css !!}</style>
@endif
