@if (count($items))
    {{-- Fixed to the viewport so it cannot be clipped by any ancestor's
         overflow, and pointer-events stay on so the link is clickable. --}}
    <div style="position:fixed;left:1rem;bottom:1rem;z-index:9998;display:flex;align-items:center;gap:.5rem;background:#0f172a;color:#fde68a;font-size:.75rem;font-weight:600;padding:.5rem .75rem;border-radius:999px;box-shadow:0 8px 24px rgba(2,6,23,.28);font-family:ui-sans-serif,system-ui,sans-serif;">
        <span style="display:inline-block;width:.5rem;height:.5rem;border-radius:999px;background:#f59e0b;"></span>
        <span>Preview Only: {{ implode(' / ', $items) }}</span>
        <a href="{{ route('themes.index') }}" style="color:#93c5fd;text-decoration:underline;">Manage</a>
    </div>
@endif
