{{-- The OAuth redirect URI to register in the provider's console. Copyable. --}}
<div x-data="{ copied: false }">
    <p class="text-sm font-medium text-slate-700">Redirect URI</p>
    <p class="mt-1 text-xs text-slate-500">Register this redirect URI in the provider's console. It must match exactly.</p>
    <div class="mt-2 flex items-center gap-2">
        <code x-ref="uri" class="min-w-0 flex-1 break-all rounded-lg bg-white/70 px-3 py-2 font-mono text-xs text-slate-700 ring-1 ring-inset ring-slate-200">{{ $uri }}</code>
        <button type="button"
                x-on:click="navigator.clipboard.writeText($refs.uri.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-300 transition hover:bg-slate-50">
            <x-icon name="copy" class="h-3.5 w-3.5" />
            <span x-text="copied ? 'Copied' : 'Copy'"></span>
        </button>
    </div>
</div>
