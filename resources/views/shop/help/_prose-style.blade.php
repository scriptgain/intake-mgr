{{-- Typographic styling for server-rendered Markdown bodies (no Tailwind
     typography plugin on the CDN build, so this is scoped by hand). Included
     once per page that renders a .shop-prose block. --}}
<style>
    .shop-prose{color:#334155;font-size:.95rem;line-height:1.75}
    .shop-prose > :first-child{margin-top:0}
    .shop-prose > :last-child{margin-bottom:0}
    .shop-prose h1,.shop-prose h2,.shop-prose h3,.shop-prose h4{color:#0f172a;font-weight:600;line-height:1.3;letter-spacing:-.01em}
    .shop-prose h1{font-size:1.6rem;margin:2rem 0 1rem}
    .shop-prose h2{font-size:1.3rem;margin:2rem 0 .85rem;padding-bottom:.4rem;border-bottom:1px solid #e2e8f0}
    .shop-prose h3{font-size:1.1rem;margin:1.6rem 0 .6rem}
    .shop-prose h4{font-size:1rem;margin:1.4rem 0 .5rem}
    .shop-prose p{margin:0 0 1rem}
    .shop-prose ul,.shop-prose ol{margin:0 0 1rem;padding-left:1.4rem}
    .shop-prose ul{list-style:disc}
    .shop-prose ol{list-style:decimal}
    .shop-prose li{margin:.35rem 0}
    .shop-prose li::marker{color:#ea580c}
    .shop-prose a{color:#c2410c;font-weight:500;text-decoration:underline;text-underline-offset:2px}
    .shop-prose a:hover{color:#9a3412}
    .shop-prose strong{color:#0f172a;font-weight:600}
    .shop-prose blockquote{margin:1.25rem 0;padding:.25rem 0 .25rem 1rem;border-left:3px solid #ea580c;color:#475569;font-style:italic}
    .shop-prose hr{margin:1.75rem 0;border:0;border-top:1px solid #e2e8f0}
    .shop-prose code{background:#f1f5f9;border-radius:.35rem;padding:.1rem .35rem;font-size:.85em;color:#0f172a}
    .shop-prose table{width:100%;border-collapse:collapse;margin:1rem 0;font-size:.9rem}
    .shop-prose th,.shop-prose td{border:1px solid #e2e8f0;padding:.5rem .75rem;text-align:left}
    .shop-prose th{background:#f8fafc;font-weight:600;color:#0f172a}
</style>
