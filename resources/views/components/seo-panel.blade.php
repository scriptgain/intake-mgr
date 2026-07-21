{{-- SEO panel for the product and collection editors. Collapsed by default.
     The Alpine component `seoPanel` is registered in public/js/shop-admin.js,
     which the admin layout loads BEFORE the Alpine CDN so alpine:init has
     something to find. --}}
<div x-data="seoPanel({
        title: @js(old('meta_title', $entity->meta_title)),
        description: @js(old('meta_description', $entity->meta_description)),
        autoTitle: @js($autoTitle),
        autoDescription: @js($autoDescription),
        limits: @js($limits),
     })"
     class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">

    <button type="button" @click="open = ! open" :aria-expanded="open.toString()"
        class="flex w-full items-start gap-3 px-5 py-4 text-left transition hover:bg-slate-50 sm:px-6">
        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-200">
            <x-icon name="globe" class="h-4 w-4" aria-hidden="true" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
                <span class="text-[15px] font-semibold text-slate-900">Search Engine Listing</span>
                @if ($hasOverrides)
                    <x-badge color="info">Customised</x-badge>
                @endif
                @if ($entity->noindex)
                    <x-badge color="warn">Excluded From Search</x-badge>
                @endif
            </span>
            <span class="mt-0.5 block text-sm text-slate-500">How this page appears in search results and link previews. Leave everything blank to use the generated listing below.</span>
        </span>
        <x-icon name="chevron-right" class="mt-2 h-4 w-4 shrink-0 text-slate-400 transition-transform" ::class="open && 'rotate-90'" aria-hidden="true" />
    </button>

    <div x-show="open" x-cloak class="border-t border-slate-100 px-5 py-5 sm:px-6">
        {{-- Live result preview. Shows exactly what the resolver will output,
             including the fallbacks, so blank fields are not a mystery. --}}
        <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-inset ring-slate-200">
            <p class="vx-eyebrow mb-2.5">Result Preview</p>
            <p class="truncate text-xs text-emerald-700">{{ $liveUrl }}</p>
            <p class="mt-1 truncate text-[17px] leading-6 text-[#1a0dab]" x-text="previewTitle"></p>
            <p class="mt-1 text-sm leading-5 text-slate-600" x-text="previewDescription"></p>
        </div>

        <div class="mt-5 space-y-5">
            <x-field label="Meta Title" for="meta_title" :error="$errors->first('meta_title')">
                <x-input id="meta_title" name="meta_title" x-model="title" maxlength="255"
                    :placeholder="$autoTitle" />
                <p class="mt-1.5 text-sm" :class="titleTone">
                    <span class="tabular" x-text="title.length"></span> Of {{ $limits['titleMax'] }} Characters
                </p>
            </x-field>

            <x-field label="Meta Description" for="meta_description" :error="$errors->first('meta_description')">
                <textarea id="meta_description" name="meta_description" rows="3" maxlength="500" x-model="description"
                    placeholder="{{ $autoDescription }}"
                    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"></textarea>
                <p class="mt-1.5 text-sm" :class="descriptionTone">
                    <span class="tabular" x-text="description.length"></span> Of {{ $limits['descriptionMax'] }} Characters
                </p>
            </x-field>

            <div class="grid grid-cols-1 gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
                <x-field label="Share Image URL" for="og_image"
                    hint="Absolute URL used for link previews. Defaults to the first product image."
                    :error="$errors->first('og_image')">
                    <x-input id="og_image" name="og_image" type="url" :value="old('og_image', $entity->og_image)" placeholder="https://" />
                </x-field>
                <x-field label="Canonical URL" for="canonical_url"
                    hint="Only set this when this page duplicates another. Blank is almost always right."
                    :error="$errors->first('canonical_url')">
                    <x-input id="canonical_url" name="canonical_url" type="url" :value="old('canonical_url', $entity->canonical_url)" placeholder="https://" />
                </x-field>
            </div>

            <div class="border-t border-slate-100 pt-5">
                <x-toggle name="noindex" :checked="old('noindex', $entity->noindex)"
                    label="Hide This Page From Search Engines"
                    description="Sends noindex and drops the page from the sitemap. The page stays live and shoppers can still reach it." />
            </div>
        </div>
    </div>
</div>
