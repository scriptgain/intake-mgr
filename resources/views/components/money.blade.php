{{-- Amount with a de-emphasised symbol and fractional part. Parsing happens in
     App\View\Components\Money, not here. --}}
<span {{ $attributes->merge(['class' => 'vx-money ' . $sizeClass()]) }}>
    @if ($negative)<span class="vx-money-sym">&minus;</span>@endif
    @if ($symbol !== '')<span class="vx-money-sym">{{ $symbol }}</span>@endif
    {{ $whole }}
    @if ($fraction !== '')<span class="vx-money-frac">{{ $fraction }}</span>@endif
</span>
