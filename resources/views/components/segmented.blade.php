@props(['label' => 'View', 'scroll' => true])
{{-- Segmented control. Wraps either <a> filter links or Alpine tab buttons;
     children supply .vx-seg-item and .is-active. On narrow screens the pill
     wraps its items to a second line (vx-seg is flex-wrap) rather than scrolling
     under an overflow clip, so the control's right border is always visible and
     the page never grows a horizontal scrollbar of its own. --}}
<div {{ $attributes->merge(['class' => $scroll ? 'min-w-0 max-w-full' : '']) }}>
    <div class="vx-seg" role="tablist" aria-label="{{ $label }}">
        {{ $slot }}
    </div>
</div>
