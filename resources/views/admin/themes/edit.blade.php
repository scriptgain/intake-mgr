<x-layouts.app :title="$theme->exists ? $theme->name : 'New Theme'">
    <x-page-header
        eyebrow="Appearance / Themes"
        :title="$theme->exists ? $theme->name : 'New Theme'"
        icon="star"
        subtitle="Tokens, not code. Everything here is applied to the storefront at runtime, with no rebuild."
        :back="['href' => route('themes.index'), 'label' => 'All Themes']">
        <x-slot:meta>
            @if ($theme->is_active)<x-badge color="success" dot>Active Theme</x-badge>@endif
            @if ($theme->is_preset)<x-badge color="neutral">Shipped Preset</x-badge>@endif
        </x-slot:meta>
        <x-slot:actions>
            @if ($theme->exists)
                <form method="POST" action="{{ route('themes.duplicate', $theme) }}">
                    @csrf
                    <x-button type="submit" variant="secondary" size="sm" icon="copy">Duplicate</x-button>
                </form>
                <x-button variant="secondary" size="sm" icon="download" href="{{ route('themes.export', $theme) }}">Export</x-button>
                @unless ($theme->is_active)
                    <form method="POST" action="{{ route('themes.preview', $theme) }}">
                        @csrf
                        <x-button type="submit" variant="secondary" size="sm" icon="eye">Preview On Site</x-button>
                    </form>
                @endunless
            @endif
        </x-slot:actions>
        <x-slot:primary>
            @if ($theme->exists && ! $theme->is_active)
                <x-confirm-action
                    name="activate-this-theme"
                    :action="route('themes.activate', $theme)"
                    title="Make This The Live Theme?"
                    :message="'Every visitor will see \'' . $theme->name . '\' immediately. You can switch back at any time.'"
                    confirm="Activate Theme"
                    confirm-icon="check">
                    <x-button icon="check-circle">Activate</x-button>
                </x-confirm-action>
            @endif
        </x-slot:primary>
    </x-page-header>

    @once
        <style>
            /* The live preview pane. Plain CSS driven by custom properties the
               themeForm component writes, so dragging a slider re-renders it
               instantly without a round trip and without saving anything. */
            .th-preview{background:var(--p-bg);color:var(--p-ink);font-family:var(--p-font);border-radius:.75rem;overflow:hidden;border:1px solid var(--p-line);}
            .th-preview-bar{background:var(--p-chrome);color:#cbd5e1;font-size:.75rem;padding:calc(var(--p-gap) * .5) var(--p-gap);display:flex;justify-content:space-between;gap:1rem;}
            .th-preview-nav{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:calc(var(--p-gap) * .75) var(--p-gap);border-bottom:1px solid var(--p-line);background:#fff;}
            .th-preview-brand{display:flex;align-items:center;gap:.5rem;font-weight:600;}
            .th-preview-body{padding:var(--p-gap);display:grid;gap:var(--p-gap);}
            .th-card{background:#fff;border:1px solid var(--p-line);border-radius:var(--p-radius);overflow:hidden;}
            .th-card-media{height:5.5rem;background:linear-gradient(135deg,var(--p-accent-soft),#fff);}
            .th-card-body{padding:calc(var(--p-gap) * .75);}
            .th-price{font-weight:600;}
            .th-muted{color:var(--p-muted);font-size:.8125rem;}
            .th-btn{display:inline-flex;align-items:center;justify-content:center;gap:.375rem;background:var(--p-accent);color:#fff;border-radius:var(--p-radius-sm);padding:.5rem .875rem;font-size:.8125rem;font-weight:500;border:0;}
            .th-btn-ghost{background:#fff;color:var(--p-ink);border:1px solid var(--p-line);}
            .th-chip{display:inline-flex;align-items:center;border-radius:999px;padding:.125rem .625rem;font-size:.6875rem;font-weight:600;background:var(--p-accent-soft);color:var(--p-accent-dark);border:1px solid var(--p-accent-line);}
            .th-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--p-gap);}
            .th-ramp{display:flex;border-radius:.5rem;overflow:hidden;border:1px solid var(--color-slate-200);}
            .th-ramp span{flex:1 1 0;height:2.25rem;}
            .th-range{width:100%;accent-color:var(--color-brand-600);}
        </style>
    @endonce

    <form method="POST" enctype="multipart/form-data"
          action="{{ $theme->exists ? route('themes.update', $theme) : route('themes.store') }}"
          x-data="themeForm({
              tokens: @js($tokens),
              deriveRamp: @js(empty($tokens['ramp'])),
              hasCustomRamp: @js(! empty($tokens['ramp'])),
              logo: @js($theme->logoUrl()),
              favicon: @js($theme->faviconUrl())
          })"
          class="space-y-6">
        @csrf
        @if ($theme->exists)@method('PUT')@endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

            {{-- ---------------- Controls ---------------- --}}
            <div class="min-w-0 space-y-6 lg:col-span-3">
                <x-card title="Identity">
                    <div class="space-y-5">
                        <x-field label="Theme Name" for="name" required :error="$errors->first('name')">
                            <x-input id="name" name="name" :value="old('name', $theme->name)" maxlength="60" required />
                        </x-field>
                        <x-field label="Description" for="description" hint="A note to your future self about where this theme suits." :error="$errors->first('description')">
                            <x-input id="description" name="description" :value="old('description', $theme->description)" maxlength="200" />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Brand Colour" subtitle="The accent drives the whole eleven-step brand ramp used across buttons, links, badges and focus rings.">
                    <div class="space-y-5">
                        <x-field label="Accent Colour" for="accent" required :error="$errors->first('accent')">
                            <div class="flex items-center gap-3">
                                <input type="color" x-model="t.accent" aria-label="Accent Colour Picker" class="h-10 w-14 shrink-0 rounded-lg border border-slate-300 bg-white p-1">
                                <x-input id="accent" name="accent" x-model="t.accent" class="font-mono" maxlength="7" />
                            </div>
                        </x-field>

                        <div>
                            <p class="mb-2 text-sm font-medium text-slate-700">Generated Ramp</p>
                            <div class="th-ramp">
                                <template x-for="step in [50,100,200,300,400,500,600,700,800,900,950]" :key="step">
                                    <span :style="`background:${ramp(step)}`" :title="`brand-${step}`"></span>
                                </template>
                            </div>
                        </div>

                        @if ($tokens['ramp'])
                            <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-inset ring-slate-200">
                                <label class="flex cursor-pointer select-none items-start gap-3">
                                    <input type="hidden" name="derive_ramp" :value="deriveRamp ? 1 : 0">
                                    <button type="button" role="switch" :aria-checked="deriveRamp.toString()" x-on:click="deriveRamp = ! deriveRamp"
                                            :class="deriveRamp ? 'bg-brand-600' : 'bg-slate-300'"
                                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors">
                                        <span :class="deriveRamp ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                                    </button>
                                    <span class="text-sm">
                                        <span class="font-medium text-slate-900">Derive The Ramp From The Accent</span>
                                        <span class="block text-slate-500">
                                            This theme carries a hand-tuned colour scale, which is why changing the accent above does not move it.
                                            Switch this on to replace that scale with one generated from your accent.
                                        </span>
                                    </span>
                                </label>
                            </div>
                        @else
                            <input type="hidden" name="derive_ramp" value="1">
                        @endif
                    </div>
                </x-card>

                <x-card title="Surfaces" subtitle="The dark utility bar at the top of every page, and the storefront's own paper.">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-field label="Top Bar" for="chrome" :error="$errors->first('chrome')">
                            <div class="flex items-center gap-3">
                                <input type="color" x-model="t.chrome" aria-label="Top Bar Colour Picker" class="h-10 w-14 shrink-0 rounded-lg border border-slate-300 bg-white p-1">
                                <x-input id="chrome" name="chrome" x-model="t.chrome" class="font-mono" maxlength="7" />
                            </div>
                        </x-field>
                        <x-field label="Top Bar (Soft)" for="chrome_soft" :error="$errors->first('chrome_soft')">
                            <div class="flex items-center gap-3">
                                <input type="color" x-model="t.chrome_soft" aria-label="Soft Top Bar Colour Picker" class="h-10 w-14 shrink-0 rounded-lg border border-slate-300 bg-white p-1">
                                <x-input id="chrome_soft" name="chrome_soft" x-model="t.chrome_soft" class="font-mono" maxlength="7" />
                            </div>
                        </x-field>
                        <x-field label="Page Background" for="shop_bg" :error="$errors->first('shop_bg')">
                            <div class="flex items-center gap-3">
                                <input type="color" x-model="t.shop_bg" aria-label="Page Background Colour Picker" class="h-10 w-14 shrink-0 rounded-lg border border-slate-300 bg-white p-1">
                                <x-input id="shop_bg" name="shop_bg" x-model="t.shop_bg" class="font-mono" maxlength="7" />
                            </div>
                        </x-field>
                        <x-field label="Body Text" for="shop_ink" :error="$errors->first('shop_ink')">
                            <div class="flex items-center gap-3">
                                <input type="color" x-model="t.shop_ink" aria-label="Body Text Colour Picker" class="h-10 w-14 shrink-0 rounded-lg border border-slate-300 bg-white p-1">
                                <x-input id="shop_ink" name="shop_ink" x-model="t.shop_ink" class="font-mono" maxlength="7" />
                            </div>
                        </x-field>
                        <x-field label="Muted Text" for="shop_muted" :error="$errors->first('shop_muted')">
                            <div class="flex items-center gap-3">
                                <input type="color" x-model="t.shop_muted" aria-label="Muted Text Colour Picker" class="h-10 w-14 shrink-0 rounded-lg border border-slate-300 bg-white p-1">
                                <x-input id="shop_muted" name="shop_muted" x-model="t.shop_muted" class="font-mono" maxlength="7" />
                            </div>
                        </x-field>
                        <x-field label="Hairlines" for="shop_line" :error="$errors->first('shop_line')">
                            <div class="flex items-center gap-3">
                                <input type="color" x-model="t.shop_line" aria-label="Hairline Colour Picker" class="h-10 w-14 shrink-0 rounded-lg border border-slate-300 bg-white p-1">
                                <x-input id="shop_line" name="shop_line" x-model="t.shop_line" class="font-mono" maxlength="7" />
                            </div>
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Typography, Corners And Rhythm">
                    <div class="space-y-6">
                        <x-field label="Typeface" for="font_family" hint="Every option is bundled with ShopMGR or built into the device. Nothing here loads a font from a third party." :error="$errors->first('font_family')">
                            <x-select id="font_family" name="font_family" x-model="t.font_family">
                                @foreach ($fonts as $value => $label)
                                    <option value="{{ $value }}" @selected(old('font_family', $tokens['font_family']) === $value)>{{ $label }}</option>
                                @endforeach
                            </x-select>
                        </x-field>

                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="font_scale" class="block text-sm font-medium text-slate-700">Type Scale</label>
                                <span class="tabular text-sm text-slate-500"><span x-text="t.font_scale"></span>%</span>
                            </div>
                            <input id="font_scale" name="font_scale" type="range" min="85" max="125" step="1" x-model="t.font_scale" class="th-range">
                            <p class="mt-1.5 text-sm text-slate-500">Scales the whole storefront proportionally, text and spacing together.</p>
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="radius" class="block text-sm font-medium text-slate-700">Corner Radius</label>
                                <span class="tabular text-sm text-slate-500"><span x-text="t.radius"></span>%</span>
                            </div>
                            <input id="radius" name="radius" type="range" min="0" max="220" step="5" x-model="t.radius" class="th-range">
                            <p class="mt-1.5 text-sm text-slate-500">0% is fully square, 100% is what ShopMGR ships, 220% is very soft.</p>
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="spacing" class="block text-sm font-medium text-slate-700">Spacing Rhythm</label>
                                <span class="tabular text-sm text-slate-500"><span x-text="t.spacing"></span>%</span>
                            </div>
                            <input id="spacing" name="spacing" type="range" min="70" max="160" step="5" x-model="t.spacing" class="th-range">
                            <p class="mt-1.5 text-sm text-slate-500">Tightens or opens up every gap, pad and margin at once.</p>
                        </div>
                    </div>
                </x-card>

                <x-card title="Logo And Favicon" subtitle="Optional. Leave both empty to use the built-in wordmark and generated icon.">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <x-field label="Logo" for="logo" hint="PNG or SVG, up to 1 MB. Shown in the storefront header." :error="$errors->first('logo')">
                            <input id="logo" type="file" name="logo" accept="image/*" x-on:change="onFile($event, 'logo')"
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                            <input type="hidden" name="remove_logo" :value="removeLogo ? 1 : 0">
                            <template x-if="logoPreview">
                                <div class="mt-3 flex items-center gap-3">
                                    <img :src="logoPreview" alt="Logo preview" class="h-10 max-w-[10rem] object-contain">
                                    <button type="button" class="text-sm font-medium text-rose-600 hover:text-rose-700"
                                            x-on:click="removeLogo = true; logoPreview = null">Remove</button>
                                </div>
                            </template>
                        </x-field>

                        <x-field label="Favicon" for="favicon" hint="PNG or SVG, up to 512 KB. Shown in the browser tab." :error="$errors->first('favicon')">
                            <input id="favicon" type="file" name="favicon" accept="image/*" x-on:change="onFile($event, 'favicon')"
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                            <input type="hidden" name="remove_favicon" :value="removeFavicon ? 1 : 0">
                            <template x-if="faviconPreview">
                                <div class="mt-3 flex items-center gap-3">
                                    <img :src="faviconPreview" alt="Favicon preview" class="h-8 w-8 rounded object-contain ring-1 ring-slate-200">
                                    <button type="button" class="text-sm font-medium text-rose-600 hover:text-rose-700"
                                            x-on:click="removeFavicon = true; faviconPreview = null">Remove</button>
                                </div>
                            </template>
                        </x-field>
                    </div>
                </x-card>
            </div>

            {{-- ---------------- Live preview ---------------- --}}
            <div class="min-w-0 lg:col-span-2">
                <div class="lg:sticky lg:top-20">
                    <x-card title="Live Preview" subtitle="Updates as you type. Nothing is saved until you press Save.">
                        <div class="th-preview" :style="previewStyle()">
                            <div class="th-preview-bar">
                                <span>Free Shipping Over $50</span>
                                <span>Cart (2)</span>
                            </div>
                            <div class="th-preview-nav">
                                <span class="th-preview-brand">
                                    <template x-if="logoPreview">
                                        <img :src="logoPreview" alt="" style="height:1.5rem;max-width:7rem;object-fit:contain">
                                    </template>
                                    <template x-if="! logoPreview">
                                        <span :style="`color:${t.accent}`">{{ config('shop.store_name') }}</span>
                                    </template>
                                </span>
                                <span class="th-muted">Search</span>
                            </div>
                            <div class="th-preview-body">
                                <div>
                                    <span class="th-chip">New Season</span>
                                    <h4 style="margin-top:.5rem;font-weight:600">Everyday Canvas Tote</h4>
                                    <p class="th-muted" style="margin-top:.25rem">Heavyweight cotton, reinforced base, made to be overloaded.</p>
                                </div>
                                <div class="th-grid">
                                    <div class="th-card">
                                        <div class="th-card-media"></div>
                                        <div class="th-card-body">
                                            <p style="font-size:.8125rem">Canvas Tote</p>
                                            <p class="th-price">$48.00</p>
                                        </div>
                                    </div>
                                    <div class="th-card">
                                        <div class="th-card-media"></div>
                                        <div class="th-card-body">
                                            <p style="font-size:.8125rem">Field Cap</p>
                                            <p class="th-price">$32.00</p>
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                                    <button type="button" class="th-btn">Add To Cart</button>
                                    <button type="button" class="th-btn th-btn-ghost">View Details</button>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-sm text-slate-500">
                            This is an approximation of the storefront's chrome. To see the theme on the real pages,
                            @if ($theme->exists)
                                use Preview On Site above.
                            @else
                                save it first, then use Preview On Site.
                            @endif
                        </p>
                    </x-card>
                </div>
            </div>
        </div>

        <div class="section-divider"></div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-button variant="secondary" href="{{ route('themes.index') }}">Cancel</x-button>
            <x-button type="submit" icon="check">{{ $theme->exists ? 'Save Theme' : 'Create Theme' }}</x-button>
        </div>
    </form>
</x-layouts.app>
