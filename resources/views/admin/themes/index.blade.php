<x-layouts.app title="Themes">
    <x-page-header
        eyebrow="Appearance"
        title="Themes"
        icon="star"
        subtitle="Colour, typography, corners and spacing for your storefront. Switch the whole look in one click.">
        <x-slot:actions>
            @if (auth()->user()?->isAdmin())
                <x-button variant="secondary" icon="edit" href="{{ route('templates.index') }}">Templates</x-button>
            @endif
            <x-button variant="secondary" icon="download" x-data x-on:click="$dispatch('open-modal', 'import-theme')">Import</x-button>
        </x-slot:actions>
        <x-slot:primary>
            <x-button href="{{ route('themes.create') }}" icon="plus">New Theme</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($previewId)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-inset ring-amber-200">
            <div class="flex min-w-0 items-start gap-3">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-amber-600 ring-1 ring-amber-200">
                    <x-icon name="eye" class="h-5 w-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-amber-900">Theme Preview Active</p>
                    <p class="mt-0.5 text-sm text-amber-800">You are seeing a theme that is not live. Your customers still see the active one.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('themes.preview.stop') }}">
                @csrf
                <x-button type="submit" variant="secondary" size="sm" icon="x-circle">Stop Preview</x-button>
            </form>
        </div>
    @endif

    <div x-data="{
            selected: [],
            allIds: [{{ $themes->where('is_preset', false)->where('is_active', false)->pluck('id')->implode(',') }}],
            submitBulk() {
                const form = this.$refs.bulkForm;
                form.querySelectorAll('input.js-dyn').forEach(node => node.remove());
                this.selected.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden'; input.name = 'ids[]'; input.value = id; input.className = 'js-dyn';
                    form.appendChild(input);
                });
                form.submit();
            }
        }">
        <form method="POST" action="{{ route('themes.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

        {{-- Bulk bar, shown once themes are selected. --}}
        <div x-show="selected.length" x-cloak class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-2.5">
            <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
            <div class="flex items-center gap-2">
                <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                <x-button type="button" variant="danger" size="sm" icon="trash" x-on:click="$dispatch('open-modal', 'bulk-delete-themes')">Delete Selected</x-button>
            </div>
        </div>

        {{-- Card grid: themes are visual, so a grid reads better than a table
             and never needs to scroll sideways. --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($themes as $theme)
                <div @class([
                    'relative flex min-w-0 flex-col rounded-2xl bg-white p-5 ring-1 ring-inset transition',
                    'ring-2 ring-brand-400' => $theme->is_active,
                    'ring-slate-200 hover:ring-slate-300' => ! $theme->is_active,
                ])>
                    @if (! $theme->is_preset && ! $theme->is_active)
                        <div class="absolute right-4 top-4">@include('admin._select-toggle', ['id' => $theme->id])</div>
                    @endif

                    <div class="min-w-0 pr-8">
                        <a href="{{ route('themes.edit', $theme) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $theme->name }}</a>
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ $theme->typographyLabel() }}</p>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        @if ($theme->is_active)<x-badge color="success" dot>Active</x-badge>@endif
                        @if ($theme->id === $previewId)<x-badge color="warn" dot>Previewing</x-badge>@endif
                        @if ($theme->is_preset)<x-badge color="neutral">Shipped Preset</x-badge>@endif
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-1.5">
                        @foreach ($theme->swatches() as $swatch)
                            <span class="inline-block h-7 w-7 rounded-lg ring-1 ring-inset ring-slate-200"
                                  style="background: {{ $swatch['color'] }}"
                                  data-tip="{{ $swatch['label'] }}: {{ $swatch['color'] }}"></span>
                        @endforeach
                    </div>

                    @if ($theme->description)
                        <p class="mt-3 text-sm leading-relaxed text-slate-500">{{ $theme->description }}</p>
                    @endif

                    <div class="mt-auto flex flex-wrap items-center gap-1 border-t border-slate-100 pt-4">
                        @unless ($theme->is_active)
                            <form method="POST" action="{{ route('themes.preview', $theme) }}">
                                @csrf
                                <x-icon-button type="submit" icon="eye" title="Preview This Theme" />
                            </form>
                            <x-confirm-action
                                name="activate-theme-{{ $theme->id }}"
                                :action="route('themes.activate', $theme)"
                                title="Make This The Live Theme?"
                                :message="'Every visitor will see \'' . $theme->name . '\' immediately. Nothing else changes: your products, orders and templates are untouched, and you can switch back at any time.'"
                                confirm="Activate Theme"
                                confirm-icon="check">
                                <x-icon-button icon="check-circle" title="Activate Theme" variant="brand" />
                            </x-confirm-action>
                        @endunless
                        <x-icon-button href="{{ route('themes.edit', $theme) }}" icon="edit" title="Edit Theme" />
                        <form method="POST" action="{{ route('themes.duplicate', $theme) }}">
                            @csrf
                            <x-icon-button type="submit" icon="copy" title="Duplicate Theme" />
                        </form>
                        <x-icon-button href="{{ route('themes.export', $theme) }}" icon="download" title="Export As JSON" />
                        @if (! $theme->is_preset && ! $theme->is_active)
                            <x-delete-button
                                :action="route('themes.destroy', $theme)"
                                name="del-theme-{{ $theme->id }}"
                                label="Delete Theme"
                                title="Delete This Theme?"
                                :message="'\'' . $theme->name . '\' will be removed. Your storefront is not affected because this theme is not active. Export it first if you might want it back.'" />
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <x-modal name="bulk-delete-themes" title="Delete Selected Themes?" icon="warning" tone="danger" maxWidth="max-w-md">
            These themes will be removed. The active theme and shipped presets are never deleted, so anything of that
            kind in your selection is skipped. This cannot be undone.
            <x-slot:footer>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-themes')">Cancel</x-button>
                <x-button variant="danger" size="sm" icon="trash" x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-themes')">Delete Themes</x-button>
            </x-slot:footer>
        </x-modal>
    </div>

    <div class="section-divider my-8"></div>

    <x-card title="Moving A Theme Between Stores"
            subtitle="Export writes a single JSON file with every token in this theme. Import reads one back, on this install or any other.">
        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
            <x-button variant="secondary" size="sm" icon="download" x-data x-on:click="$dispatch('open-modal', 'import-theme')">Import A Theme</x-button>
            <span>Imported themes arrive switched off, so nothing changes on your storefront until you activate them.</span>
        </div>
    </x-card>

    <x-modal name="import-theme" title="Import A Theme" subtitle="Upload a ShopMGR theme export, or paste its JSON." icon="download" maxWidth="max-w-lg">
        <form method="POST" action="{{ route('themes.import') }}" enctype="multipart/form-data" id="import-theme-form" class="space-y-4">
            @csrf
            <x-field label="Theme File" for="theme-file" hint="A .json file exported from ShopMGR.">
                <input id="theme-file" type="file" name="file" accept="application/json,.json"
                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
            </x-field>
            <div class="flex items-center gap-3">
                <span class="h-px flex-1 bg-slate-200"></span>
                <span class="text-xs font-medium uppercase tracking-wide text-slate-400">Or Paste</span>
                <span class="h-px flex-1 bg-slate-200"></span>
            </div>
            <x-field label="Theme JSON" for="theme-json">
                <textarea id="theme-json" name="json" rows="6" placeholder='{"format":"shopmgr.theme", ...}'
                          class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-xs text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500"></textarea>
            </x-field>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'import-theme')">Cancel</x-button>
            <x-button size="sm" icon="download" type="submit" form="import-theme-form">Import Theme</x-button>
        </x-slot:footer>
    </x-modal>
</x-layouts.app>
