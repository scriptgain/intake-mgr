@if ($scheduled)
    <div class="relative inline-block" x-data="{ open: false }" @keydown.escape="open = false">
        <button type="button" @click="open = !open" @click.outside="open = false"
                class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-50">
            <x-icon name="clock" class="h-4 w-4 shrink-0 text-slate-500" />
            Add To Calendar
            <x-icon name="chevron-down" class="h-3.5 w-3.5 shrink-0 text-slate-400 transition" x-bind:class="open && 'rotate-180'" />
        </button>

        <div x-show="open" x-cloak x-transition.opacity
             class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-xl bg-white py-1.5 shadow-lg ring-1 ring-black/5">
            <a href="{{ $icsUrl }}"
               class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                <x-icon name="download" class="h-4 w-4 shrink-0 text-slate-400" />
                Apple / Download (.ics)
            </a>
            <a href="{{ $googleUrl }}" target="_blank" rel="noopener"
               class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                <x-icon name="external" class="h-4 w-4 shrink-0 text-slate-400" />
                Google Calendar
            </a>
            <a href="{{ $outlookUrl }}" target="_blank" rel="noopener"
               class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50">
                <x-icon name="external" class="h-4 w-4 shrink-0 text-slate-400" />
                Outlook
            </a>
        </div>
    </div>
@endif
