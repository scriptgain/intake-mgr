{{-- Rendered by App\View\Components\Seo. Every value below is already final:
     no fallbacks, no formatting, no logic. The title separator is a colon. --}}
<title>{{ $seo->title }}</title>
@if ($seo->description)
    <meta name="description" content="{{ $seo->description }}">
@endif
<meta name="robots" content="{{ $seo->robots }}">
@if ($seo->canonical)
    <link rel="canonical" href="{{ $seo->canonical }}">
@endif
@foreach ($seo->links as $rel => $href)
    <link rel="{{ $rel }}" href="{{ $href }}">
@endforeach
@foreach ($seo->verifications as $name => $content)
    <meta name="{{ $name }}" content="{{ $content }}">
@endforeach
@foreach ($seo->og as $property => $content)
    <meta property="{{ $property }}" content="{{ $content }}">
@endforeach
@foreach ($seo->twitter as $name => $content)
    <meta name="{{ $name }}" content="{{ $content }}">
@endforeach
@if ($jsonLd)
    <script type="application/ld+json">{!! $jsonLd !!}</script>
@endif
