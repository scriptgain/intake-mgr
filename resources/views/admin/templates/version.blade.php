<x-layouts.app :title="'Version #' . $version->id">
    <x-page-header
        eyebrow="Appearance / Templates"
        :title="$meta['label'] . ' / Version #' . $version->id"
        icon="clock"
        subtitle="What this version would change, compared with the template that is live right now."
        :back="['href' => route('templates.edit', $meta['view']), 'label' => 'Back To Editor']">
        <x-slot:meta>
            <x-badge :color="$version->tone()">{{ $version->label() }}</x-badge>
            <span class="text-xs text-slate-500">{{ $version->user?->name ?? 'System' }} &middot; {{ $version->created_at?->format(config('shop.date_format').' '.config('shop.time_format')) }}</span>
            @if ($version->note)<span class="text-xs text-slate-500">{{ $version->note }}</span>@endif
        </x-slot:meta>
        <x-slot:primary>
            @if ($canRevert)
                <x-confirm-action
                    name="revert-version"
                    :action="route('templates.revert', [$meta['view'], $version])"
                    tone="warn"
                    title="Revert To This Version?"
                    :message="'Version #' . $version->id . ' will be checked and published as the live version of ' . $meta['label'] . '. This is recorded as a new version, so nothing is lost.'"
                    confirm="Revert"
                    confirm-icon="restore">
                    <x-button icon="restore">Revert To This Version</x-button>
                </x-confirm-action>
            @endif
        </x-slot:primary>
    </x-page-header>

    @unless ($canRevert)
        <div class="mb-6 flex items-start gap-3.5 rounded-xl bg-slate-50 px-4 py-3.5 ring-1 ring-inset ring-slate-200">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-500 ring-1 ring-slate-200">
                <x-icon name="info" class="h-5 w-5" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">This Entry Is A Reset, Not A Saved Template</p>
                <p class="mt-0.5 text-sm text-slate-600">It records the moment this template was put back to the shipped default, so there is no source to revert to. Pick an earlier saved version instead.</p>
            </div>
        </div>
    @endunless

    <x-card flush>
        <x-diff :diff="$diff" :left-label="'Version #' . $version->id" right-label="Live Now" />
    </x-card>
</x-layouts.app>
