@if ($custom)
    <link rel="icon" href="{{ $custom }}">
    <link rel="apple-touch-icon" href="{{ $custom }}">
@else
    <link rel="icon" type="image/svg+xml" href="{{ route('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ route('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ route('favicon.apple') }}">
@endif
