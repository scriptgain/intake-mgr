<x-layouts.app title="SEO">
    <x-page-header title="SEO" icon="globe" eyebrow="Store"
        subtitle="How the storefront presents itself to search engines and link previews.">
        <x-slot:actions>
            <x-button href="{{ $sitemapUrl }}" target="_blank" rel="noopener" variant="secondary" size="sm" icon="external">Sitemap</x-button>
            <x-button href="{{ $robotsUrl }}" target="_blank" rel="noopener" variant="secondary" size="sm" icon="external">Robots</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (config('seo.site_noindex'))
        <x-alert type="warn" title="Search Engines Are Being Discouraged" class="mb-6">
            Every storefront page is sending noindex and robots.txt is disallowing the whole site. Switch this off on the Indexing tab before launch.
        </x-alert>
    @endif

    <form method="POST" action="{{ route('settings.seo.update') }}" x-data="{ tab: 'indexing' }">
        @csrf
        @method('PUT')

        <x-segmented label="SEO Settings" class="mb-6">
            <button type="button" role="tab" :aria-selected="(tab === 'indexing').toString()" @click="tab = 'indexing'"
                class="vx-seg-item" :class="tab === 'indexing' && 'is-active'">Indexing</button>
            <button type="button" role="tab" :aria-selected="(tab === 'defaults').toString()" @click="tab = 'defaults'"
                class="vx-seg-item" :class="tab === 'defaults' && 'is-active'">Titles &amp; Defaults</button>
            <button type="button" role="tab" :aria-selected="(tab === 'social').toString()" @click="tab = 'social'"
                class="vx-seg-item" :class="tab === 'social' && 'is-active'">Social &amp; Business</button>
            <button type="button" role="tab" :aria-selected="(tab === 'verification').toString()" @click="tab = 'verification'"
                class="vx-seg-item" :class="tab === 'verification' && 'is-active'">Verification</button>
        </x-segmented>

        {{-- Indexing --}}
        <div x-show="tab === 'indexing'" x-cloak class="space-y-6">
            <x-card title="Search Engine Visibility"
                subtitle="The one switch that has to survive a copy of this store onto a staging host.">
                <div class="space-y-5">
                    <x-toggle name="seo_site_noindex" :checked="old('seo_site_noindex', $flags['seo_site_noindex'])"
                        label="Discourage Search Engines From This Site"
                        description="Sends noindex, nofollow on every storefront page and disallows the entire site in robots.txt. Switch on for staging, off for a live store." />
                    <div class="border-t border-slate-100 pt-5">
                        <x-toggle name="seo_sitemap_include_out_of_stock" :checked="old('seo_sitemap_include_out_of_stock', $flags['seo_sitemap_include_out_of_stock'])"
                            label="Include Out-Of-Stock Products In The Sitemap"
                            description="A product that is temporarily out of stock keeps its rankings if its URL stays advertised. Switch off only if stock gaps are permanent. Ignored when the storefront is set to hide out-of-stock products, because those pages are not browsable at all." />
                    </div>
                </div>
            </x-card>

            <x-card title="Generated Files" subtitle="Both are served by the application, not from disk.">
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0">
                        <dt class="font-medium text-slate-900">Sitemap Index</dt>
                        <dd><a href="{{ $sitemapUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-medium text-brand-700 hover:text-brand-800">
                            <x-icon name="external" class="h-4 w-4 shrink-0" aria-hidden="true" /> {{ $sitemapUrl }}
                        </a></dd>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3 last:pb-0">
                        <dt class="font-medium text-slate-900">Robots</dt>
                        <dd><a href="{{ $robotsUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-medium text-brand-700 hover:text-brand-800">
                            <x-icon name="external" class="h-4 w-4 shrink-0" aria-hidden="true" /> {{ $robotsUrl }}
                        </a></dd>
                    </div>
                </dl>
            </x-card>
        </div>

        {{-- Titles & Defaults --}}
        <div x-show="tab === 'defaults'" x-cloak class="space-y-6">
            <x-card title="Titles" subtitle="Applied to every page that does not carry its own title.">
                <div class="space-y-5">
                    <x-field label="Title Template" for="seo_title_template" required
                        hint="Use {title} for the page and {store} for the store name. The store name is never doubled on the home page."
                        :error="$errors->first('seo_title_template')">
                        <x-input id="seo_title_template" name="seo_title_template" :value="old('seo_title_template', $values['seo_title_template'])" required />
                    </x-field>
                    <x-field label="Default Meta Description" for="seo_default_description"
                        hint="Used on pages with nothing more specific to say. Products and collections generate their own."
                        :error="$errors->first('seo_default_description')">
                        <textarea id="seo_default_description" name="seo_default_description" rows="3" maxlength="320"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('seo_default_description', $values['seo_default_description']) }}</textarea>
                    </x-field>
                </div>
            </x-card>

            <x-card title="Product Data" subtitle="Applied to the structured data on every product offer.">
                <x-field label="Item Condition" for="seo_item_condition" required
                    hint="Sent as schema.org itemCondition on every Offer. Change once here if the store sells used or refurbished goods."
                    :error="$errors->first('seo_item_condition')">
                    <x-select id="seo_item_condition" name="seo_item_condition">
                        @foreach ($conditionOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('seo_item_condition', $values['seo_item_condition']) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </x-field>
            </x-card>
        </div>

        {{-- Social & Business --}}
        <div x-show="tab === 'social'" x-cloak class="space-y-6">
            <x-card title="Link Previews" subtitle="What a shared link looks like on social platforms and in chat.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Twitter Card Type" for="seo_twitter_card" required :error="$errors->first('seo_twitter_card')">
                        <x-select id="seo_twitter_card" name="seo_twitter_card">
                            @foreach ($cardOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('seo_twitter_card', $values['seo_twitter_card']) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Twitter Handle" for="seo_twitter_site" hint="Including the @ sign." :error="$errors->first('seo_twitter_site')">
                        <x-input id="seo_twitter_site" name="seo_twitter_site" :value="old('seo_twitter_site', $values['seo_twitter_site'])" placeholder="@yourstore" />
                    </x-field>
                    <x-field label="Default Share Image" for="seo_default_og_image" class="sm:col-span-2"
                        hint="Absolute URL. Used when a page has no image of its own. Products always prefer their own photo."
                        :error="$errors->first('seo_default_og_image')">
                        <x-input id="seo_default_og_image" name="seo_default_og_image" type="url" :value="old('seo_default_og_image', $values['seo_default_og_image'])" placeholder="https://" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Business Identity" subtitle="Emitted as Organization structured data on the home page.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Organization Name" for="seo_organization_name" hint="Leave blank to use the store name." :error="$errors->first('seo_organization_name')">
                        <x-input id="seo_organization_name" name="seo_organization_name" :value="old('seo_organization_name', $values['seo_organization_name'])" />
                    </x-field>
                    <x-field label="Logo URL" for="seo_organization_logo" hint="Absolute URL to a square logo." :error="$errors->first('seo_organization_logo')">
                        <x-input id="seo_organization_logo" name="seo_organization_logo" type="url" :value="old('seo_organization_logo', $values['seo_organization_logo'])" placeholder="https://" />
                    </x-field>
                    <x-field label="Profile URLs" for="seo_organization_sameas" class="sm:col-span-2"
                        hint="One URL per line. Social and directory profiles that belong to this business."
                        :error="$errors->first('seo_organization_sameas')">
                        <textarea id="seo_organization_sameas" name="seo_organization_sameas" rows="4"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('seo_organization_sameas', $values['seo_organization_sameas']) }}</textarea>
                    </x-field>
                </div>
            </x-card>
        </div>

        {{-- Verification --}}
        <div x-show="tab === 'verification'" x-cloak>
            <x-card title="Search Console Verification" subtitle="Ownership tokens, rendered as meta tags on every storefront page.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Google Verification Token" for="seo_google_verification" :error="$errors->first('seo_google_verification')">
                        <x-input id="seo_google_verification" name="seo_google_verification" :value="old('seo_google_verification', $values['seo_google_verification'])" class="font-mono" />
                    </x-field>
                    <x-field label="Bing Verification Token" for="seo_bing_verification" :error="$errors->first('seo_bing_verification')">
                        <x-input id="seo_bing_verification" name="seo_bing_verification" :value="old('seo_bing_verification', $values['seo_bing_verification'])" class="font-mono" />
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="mt-6 flex justify-end">
            <x-button type="submit" icon="check">Save Changes</x-button>
        </div>
    </form>
</x-layouts.app>
