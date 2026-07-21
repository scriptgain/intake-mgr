@props(['label' => 'View', 'scroll' => true])
{{-- Segmented control. Wraps either <a> filter links or Alpine tab buttons;
     children supply .vx-seg-item and .is-active. Scrolls rather than wraps on
     narrow screens so the control stays one line and the page never grows a
     horizontal scrollbar of its own. --}}
<div {{ $attributes->merge(['class' => $scroll ? 'no-scrollbar -mx-1 max-w-full overflow-x-auto px-1' : '']) }}>
    <div class="vx-seg" role="tablist" aria-label="{{ $label }}">
        {{ $slot }}
    </div>
</div>
