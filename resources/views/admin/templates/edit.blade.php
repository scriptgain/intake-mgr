<x-layouts.app :title="$meta['label']">
    <x-page-header
        eyebrow="Appearance / Templates"
        :title="$meta['label']"
        icon="edit"
        :subtitle="$meta['description']"
        :back="['href' => route('templates.index'), 'label' => 'All Templates']">
        <x-slot:meta>
            <span class="font-mono text-xs text-slate-400">{{ $meta['view'] }}</span>
            @if ($meta['risk'] === 'high')
                <x-badge color="warn" dot>High Risk</x-badge>
            @endif
            @if ($override)
                <x-badge color="info" dot>Customised</x-badge>
            @else
                <x-badge color="neutral">Shipped Default</x-badge>
            @endif
            @if ($previewing)
                <x-badge color="warn" dot>Preview Active</x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="external" href="{{ route('shop.home') }}" target="_blank" rel="noopener">View Website</x-button>
            @if ($override)
                <x-confirm-action
                    name="reset-template"
                    :action="route('templates.reset', $meta['view'])"
                    method="DELETE"
                    tone="danger"
                    title="Reset To The Shipped Default?"
                    :message="'Your customised version of ' . $meta['label'] . ' will stop being used immediately and the template ShopMGR ships will take over. Your edit history is kept, so you can still bring any earlier version back afterwards.'"
                    confirm="Reset Template"
                    confirm-variant="danger"
                    confirm-icon="restore">
                    <x-button variant="secondary" size="sm" icon="restore">Reset To Default</x-button>
                </x-confirm-action>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($meta['risk'] === 'high')
        <div class="mb-6 flex items-start gap-3.5 rounded-xl bg-amber-50 px-4 py-3.5 ring-1 ring-inset ring-amber-200">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-amber-600 ring-1 ring-amber-200">
                <x-icon name="warning" class="h-5 w-5" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-900">This Template Handles Money Or Mail</p>
                <p class="mt-0.5 text-sm leading-relaxed text-amber-800">
                    A mistake here costs orders, not just looks. Nothing invalid can be saved, but valid Blade can
                    still remove something a customer needs, such as the pay button or a total. Preview it before you
                    publish, and place a test order afterwards.
                </p>
            </div>
        </div>
    @endif

    @if (session('template_error'))
        <div class="mb-6 overflow-hidden rounded-xl bg-white ring-1 ring-rose-200 shadow-sm">
            <div class="flex items-start gap-3.5 bg-rose-50 px-4 py-3.5">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-rose-600 ring-1 ring-rose-200">
                    <x-icon name="x-circle" class="h-5 w-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-rose-900">Not Saved. This Template Would Not Run.</p>
                    <p class="mt-1 break-words text-sm leading-relaxed text-rose-800">{{ session('template_error')['error'] }}</p>
                    <p class="mt-1.5 text-xs text-rose-700">
                        Your storefront is untouched and is still serving the last working version. Your edit is still
                        in the box below.
                    </p>
                </div>
            </div>
            @if (session('template_error')['excerpt'])
                <div class="vx-scroll overflow-x-auto border-t border-rose-100">
                    @foreach (session('template_error')['excerpt'] as $excerptLine)
                        <div class="flex items-start whitespace-pre font-mono text-[12.5px] leading-6 {{ $excerptLine['is_error'] ? 'bg-rose-50 text-rose-900' : 'text-slate-500' }}">
                            <span class="tabular w-14 shrink-0 select-none pr-3 text-right {{ $excerptLine['is_error'] ? 'text-rose-500' : 'text-slate-300' }}">{{ $excerptLine['number'] }}</span>
                            <span class="pr-4">{{ $excerptLine['text'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @once
        <style>
            /* The alignment contract. The textarea and the highlight layer must
               agree on every metric below or the coloured text drifts away from
               the caret. Plain CSS so nothing here depends on the browser JIT
               emitting a utility. */
            .tpl-editor{display:flex;align-items:stretch;background:#0f172a;border-radius:.75rem;overflow:hidden;}
            .tpl-gutter,.tpl-highlight,.tpl-input{
                margin:0;
                padding:1rem 0;
                border:0;
                font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,'Liberation Mono',monospace;
                font-size:13px;
                line-height:1.55;
                tab-size:4;
                white-space:pre;
                word-break:normal;
                overflow-wrap:normal;
            }
            .tpl-gutter{
                flex:0 0 3.75rem;
                padding-right:.75rem;
                text-align:right;
                color:#475569;
                background:#0b1220;
                overflow:hidden;
                user-select:none;
                font-variant-numeric:tabular-nums;
            }
            .tpl-stack{position:relative;flex:1 1 auto;min-width:0;}
            .tpl-highlight,.tpl-input{
                padding-left:1rem;
                padding-right:1rem;
                width:100%;
                height:34rem;
                overflow:auto;
            }
            .tpl-highlight{position:absolute;inset:0;color:#e2e8f0;pointer-events:none;overflow:hidden;}
            .tpl-input{
                position:relative;
                display:block;
                background:transparent;
                color:transparent;
                caret-color:#f8fafc;
                resize:vertical;
                outline:none;
            }
            .tpl-input::selection{background:rgba(225,29,72,.35);color:transparent;}
            .tpl-input:focus{outline:none;}
            /* Blade token colours. */
            .tk-comment{color:#64748b;font-style:italic;}
            .tk-echo{color:#fda4af;}
            .tk-directive{color:#67e8f9;font-weight:600;}
            .tk-tag{color:#93c5fd;}
            .tpl-status{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;background:#0b1220;color:#94a3b8;font-size:.75rem;padding:.5rem .875rem;}
        </style>
    @endonce

    <div x-data="{ tab: 'editor' }">
        <x-segmented label="Template Views" class="mb-4">
            <button type="button" class="vx-seg-item" :class="tab === 'editor' && 'is-active'" x-on:click="tab = 'editor'" role="tab" :aria-selected="(tab === 'editor').toString()">
                <x-icon name="edit" class="h-4 w-4 shrink-0" aria-hidden="true" /> Editor
            </button>
            <button type="button" class="vx-seg-item" :class="tab === 'diff' && 'is-active'" x-on:click="tab = 'diff'" role="tab" :aria-selected="(tab === 'diff').toString()">
                <x-icon name="filter" class="h-4 w-4 shrink-0" aria-hidden="true" /> Compare With Shipped
                <span class="vx-seg-count">+{{ $diff['added'] }} / -{{ $diff['removed'] }}</span>
            </button>
            <button type="button" class="vx-seg-item" :class="tab === 'history' && 'is-active'" x-on:click="tab = 'history'" role="tab" :aria-selected="(tab === 'history').toString()">
                <x-icon name="clock" class="h-4 w-4 shrink-0" aria-hidden="true" /> Version History
                <span class="vx-seg-count">{{ $versions->count() }}</span>
            </button>
        </x-segmented>

        {{-- ---------------- Editor ---------------- --}}
        <div x-show="tab === 'editor'">
            <form method="POST" action="{{ route('templates.update', $meta['view']) }}" x-ref="form">
                @csrf
                <input type="hidden" name="_method" value="PUT" x-ref="method">

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div x-data="templateEditor()" class="min-w-0 lg:col-span-2">
                    <div class="tpl-editor">
                        <pre class="tpl-gutter" x-ref="gutter" aria-hidden="true"></pre>
                        <div class="tpl-stack">
                            <pre class="tpl-highlight" x-ref="highlight" aria-hidden="true"></pre>
                            <textarea
                                x-ref="input"
                                name="source"
                                id="template-source"
                                class="tpl-input vx-scroll"
                                spellcheck="false"
                                autocomplete="off"
                                autocapitalize="off"
                                aria-label="Template Source"
                                x-on:input="onInput()"
                                x-on:keydown="onKeydown($event)"
                                x-on:click="caret()"
                                x-on:keyup="caret()">{{ $source }}</textarea>
                        </div>
                    </div>
                    <div class="tpl-status">
                        <span>
                            Line <span class="tabular" x-text="line"></span>,
                            Column <span class="tabular" x-text="column"></span>
                            &middot; <span class="tabular" x-text="lineCount"></span> Lines
                        </span>
                        <span class="flex items-center gap-3">
                            <span x-show="dirty" x-cloak class="text-amber-300">Unsaved Changes</span>
                            <span x-show="! dirty" class="text-slate-500">No Changes</span>
                            <span class="text-slate-500">Tab Indents &middot; Shift+Tab Outdents</span>
                        </span>
                    </div>
                </div>

                {{-- ---------------- Available Variables reference ---------------- --}}
                <aside class="lg:col-span-1 min-w-0">
                    <div class="lg:sticky lg:top-20 overflow-hidden rounded-xl bg-white ring-1 ring-shop-line shadow-sm" x-data="{ ref: 'vars' }">
                        <div class="flex items-center gap-2 border-b border-shop-line bg-slate-50/70 px-3.5 py-3">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 ring-1 ring-brand-200">
                                <x-icon name="book" class="h-4 w-4" aria-hidden="true" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">Available Reference</p>
                                <p class="text-xs text-slate-500">Click To Insert At The Cursor</p>
                            </div>
                        </div>

                        {{-- Inner tabs: Variables / Components / Blade --}}
                        <div class="flex gap-1 border-b border-shop-line px-2" role="tablist" aria-label="Reference Sections">
                            <button type="button" role="tab" x-on:click="ref = 'vars'" :aria-selected="(ref === 'vars').toString()"
                                class="border-b-2 px-3 py-2.5 -mb-px text-xs font-medium transition"
                                :class="ref === 'vars' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'">Variables</button>
                            <button type="button" role="tab" x-on:click="ref = 'components'" :aria-selected="(ref === 'components').toString()"
                                class="border-b-2 px-3 py-2.5 -mb-px text-xs font-medium transition"
                                :class="ref === 'components' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'">Components</button>
                            <button type="button" role="tab" x-on:click="ref = 'blade'" :aria-selected="(ref === 'blade').toString()"
                                class="border-b-2 px-3 py-2.5 -mb-px text-xs font-medium transition"
                                :class="ref === 'blade' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'">Blade</button>
                        </div>

                        <div class="vx-scroll max-h-[30rem] overflow-y-auto p-2.5">

                            {{-- Variables --}}
                            <div x-show="ref === 'vars'">
                                <p class="px-1 pb-2 text-[11px] leading-snug text-slate-400">
                                    A guide read from the shipped template, not a runtime guarantee. Names your controller passes may differ.
                                </p>

                                <p class="px-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">In This Template</p>
                                @forelse ($reference['scoped'] as $var)
                                    <button type="button" x-on:click="tplInsertVar('{{ $var['name'] }}')" data-tip="Insert &#123;&#123; ${{ $var['name'] }} &#125;&#125; at the cursor"
                                        class="group mt-1 flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-brand-50">
                                        <code class="shrink-0 font-mono text-[12.5px] font-medium text-brand-700">${{ $var['name'] }}</code>
                                        @if ($var['desc'])<span class="text-xs leading-snug text-slate-500">{{ $var['desc'] }}</span>@endif
                                    </button>
                                @empty
                                    <p class="px-1 py-2 text-xs text-slate-400">No template-specific variables were detected in the shipped source.</p>
                                @endforelse

                                @if (count($reference['shared']))
                                    <p class="mt-4 px-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Always Available</p>
                                    <p class="px-1 pb-1 text-[11px] leading-snug text-slate-400">{{ $reference['shared_note'] }}</p>
                                    @foreach ($reference['shared'] as $var)
                                        <button type="button" x-on:click="tplInsertVar('{{ $var['name'] }}')" data-tip="Insert &#123;&#123; ${{ $var['name'] }} &#125;&#125; at the cursor"
                                            class="group mt-1 flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-brand-50">
                                            <code class="shrink-0 font-mono text-[12.5px] font-medium text-slate-700">${{ $var['name'] }}</code>
                                            @if ($var['desc'])<span class="text-xs leading-snug text-slate-500">{{ $var['desc'] }}</span>@endif
                                        </button>
                                    @endforeach
                                @endif
                            </div>

                            {{-- Components --}}
                            <div x-show="ref === 'components'" x-cloak>
                                <p class="px-1 pb-2 text-[11px] leading-snug text-slate-400">Components you can drop in. Click to insert the tag.</p>
                                @foreach ($reference['components'] as $comp)
                                    <button type="button" x-on:click="tplInsertTag('{{ $comp['tag'] }}')"
                                        @if (count($comp['attrs'])) data-tip="Attributes: {{ implode(', ', $comp['attrs']) }}" @endif
                                        class="group mt-1 flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-brand-50">
                                        <code class="shrink-0 font-mono text-[12.5px] font-medium text-brand-700">&lt;{{ $comp['tag'] }}&gt;</code>
                                        @if (count($comp['attrs']))<span class="truncate text-xs text-slate-400">{{ implode(' ', array_slice($comp['attrs'], 0, 4)) }}</span>@endif
                                    </button>
                                @endforeach
                            </div>

                            {{-- Blade + helpers --}}
                            <div x-show="ref === 'blade'" x-cloak>
                                <p class="px-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Blade Basics</p>
                                @foreach ($reference['blade'] as $b)
                                    <button type="button" data-code="{{ $b['code'] }}" x-on:click="tplCopy($event.currentTarget.dataset.code)" data-tip="Copy to clipboard"
                                        class="mt-1 block w-full rounded-lg px-2 py-1.5 text-left transition hover:bg-brand-50">
                                        <code class="block break-words font-mono text-[12px] font-medium text-slate-800">{{ $b['code'] }}</code>
                                        <span class="text-[11px] leading-snug text-slate-500">{{ $b['desc'] }}</span>
                                    </button>
                                @endforeach

                                <p class="mt-4 px-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Store Helpers</p>
                                @foreach ($reference['helpers'] as $h)
                                    <button type="button" data-code="{{ $h['code'] }}" x-on:click="tplCopy($event.currentTarget.dataset.code)" data-tip="Copy to clipboard"
                                        class="mt-1 block w-full rounded-lg px-2 py-1.5 text-left transition hover:bg-brand-50">
                                        <code class="block break-words font-mono text-[12px] font-medium text-slate-800">{{ $h['code'] }}</code>
                                        <span class="text-[11px] leading-snug text-slate-500">{{ $h['desc'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </aside>
                </div>{{-- /editor + reference grid --}}

                @once
                    <script>
                        /* Plain helpers (no Alpine.data registration): insert text at the
                           editor caret and fire the input event so the highlight layer and
                           the dirty flag update. */
                        window.tplInsert = function (text) {
                            var ta = document.getElementById('template-source');
                            if (!ta) return;
                            ta.focus();
                            var s = ta.selectionStart, e = ta.selectionEnd;
                            ta.value = ta.value.slice(0, s) + text + ta.value.slice(e);
                            var pos = s + text.length;
                            ta.selectionStart = ta.selectionEnd = pos;
                            ta.dispatchEvent(new Event('input', { bubbles: true }));
                        };
                        window.tplInsertVar = function (n) { window.tplInsert('{' + '{ $' + n + ' }' + '}'); };
                        window.tplInsertTag = function (t) { window.tplInsert('<' + t + '></' + t + '>'); };
                        window.tplCopy = function (t) { if (navigator.clipboard) navigator.clipboard.writeText(t); };
                    </script>
                @endonce

                <div class="section-divider my-6"></div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="min-w-0 lg:col-span-2">
                        <x-field label="Change Note" for="note" hint="Optional. Shown in the version history so you can find this edit later.">
                            <x-input id="note" name="note" maxlength="160" placeholder="e.g. Added a size guide link under the buy button" />
                        </x-field>
                    </div>
                    <div class="flex items-end justify-end gap-2">
                        <x-button type="button" variant="secondary" icon="eye"
                                  x-on:click="$refs.method.value = 'POST'; $refs.form.action = '{{ route('templates.preview', $meta['view']) }}'; $refs.form.submit()">
                            Preview
                        </x-button>
                        <x-button type="submit" icon="check">Save &amp; Publish</x-button>
                    </div>
                </div>
            </form>

            <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1.5"><x-icon name="check-circle" class="h-4 w-4 shrink-0 text-emerald-500" aria-hidden="true" /> Compiled and parse-checked before saving</span>
                <span class="inline-flex items-center gap-1.5"><x-icon name="clock" class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" /> Every save is versioned and revertable</span>
                <span class="inline-flex items-center gap-1.5"><x-icon name="database" class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" /> Stored in the database, safe from updates</span>
            </div>
        </div>

        {{-- ---------------- Diff vs shipped ---------------- --}}
        <div x-show="tab === 'diff'" x-cloak>
            <x-card flush>
                <x-diff :diff="$diff" left-label="Shipped Default" right-label="Live Now" />
            </x-card>
        </div>

        {{-- ---------------- History ---------------- --}}
        <div x-show="tab === 'history'" x-cloak>
            <x-data-surface>
                @if ($versions->isEmpty())
                    <x-empty-state icon="clock" title="No Edit History Yet"
                        description="Every time you save this template a version is recorded here, with who saved it and when. You can compare any version against what is live and put it back with one click."
                        :steps="[
                            'Make a change in the editor and save it.',
                            'Come back here to compare that version against the live one.',
                            'Revert to any earlier version if you change your mind.',
                        ]" />
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>What Happened</th>
                                <th>By</th>
                                <th>When</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($versions as $version)
                                <tr class="vx-rail vx-rail-{{ $version->action === 'reset' ? 'danger' : 'none' }}">
                                    <td>
                                        <span class="font-mono font-medium text-slate-900">#{{ $version->id }}</span>
                                        @if ($version->note)<span class="mt-0.5 block text-xs text-slate-500">{{ $version->note }}</span>@endif
                                    </td>
                                    <td><x-badge :color="$version->tone()">{{ $version->label() }}</x-badge></td>
                                    <td class="text-slate-600">{{ $version->user?->name ?? 'System' }}</td>
                                    <td class="text-slate-500">{{ $version->created_at?->format(config('shop.date_format').' '.config('shop.time_format')) }}</td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-button href="{{ route('templates.version', [$meta['view'], $version]) }}" icon="eye" title="Compare With Live" />
                                            @if ($version->source !== null)
                                                <x-confirm-action
                                                    name="revert-{{ $version->id }}"
                                                    :action="route('templates.revert', [$meta['view'], $version])"
                                                    tone="warn"
                                                    title="Revert To This Version?"
                                                    :message="'Version #' . $version->id . ' will be checked and published as the live version of ' . $meta['label'] . '. Nothing is deleted: this is recorded as a new version, so you can revert the revert.'"
                                                    confirm="Revert"
                                                    confirm-icon="restore">
                                                    <x-icon-button icon="restore" title="Revert To This Version" />
                                                </x-confirm-action>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>
        </div>
    </div>
</x-layouts.app>
