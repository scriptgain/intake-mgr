{{-- Tailwind v4 + Alpine, served from CDN. No Vite build step.

     The design tokens come from App\View\Components\TailwindCdn, which stitches
     resources/css/app.css together with the active theme's tokens. This file is
     markup only (house rule), and keeping @theme/@apply out of the Blade source
     also stops Blade from mistaking them for directives. --}}
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<style type="text/tailwindcss">{!! $tokens !!}</style>
{{-- Alpine powers dropdowns, toggles, modals. The focus plugin must load before core. --}}
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
