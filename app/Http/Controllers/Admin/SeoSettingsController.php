<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Site-wide SEO settings. Everything is written to the settings table and
 * applied over config/seo.php at boot (DB-driven config, never .env).
 */
class SeoSettingsController extends Controller
{
    private const TEXT_KEYS = [
        'seo_title_template', 'seo_default_description', 'seo_default_og_image',
        'seo_twitter_card', 'seo_twitter_site',
        'seo_organization_name', 'seo_organization_logo', 'seo_organization_sameas',
        'seo_item_condition',
        'seo_google_verification', 'seo_bing_verification',
    ];

    private const BOOL_KEYS = [
        'seo_site_noindex', 'seo_sitemap_include_out_of_stock',
    ];

    public function edit()
    {
        $settings = Setting::map();

        return view('admin.settings.seo', [
            // Resolved current values, so the view is markup only: no @php
            // block, no closures, just old('key', $values['key']).
            'values' => [
                'seo_title_template' => $settings['seo_title_template'] ?? config('seo.title_template'),
                'seo_default_description' => $settings['seo_default_description'] ?? '',
                'seo_default_og_image' => $settings['seo_default_og_image'] ?? '',
                'seo_twitter_card' => $settings['seo_twitter_card'] ?? config('seo.twitter_card'),
                'seo_twitter_site' => $settings['seo_twitter_site'] ?? '',
                'seo_organization_name' => $settings['seo_organization_name'] ?? '',
                'seo_organization_logo' => $settings['seo_organization_logo'] ?? '',
                'seo_organization_sameas' => $settings['seo_organization_sameas'] ?? '',
                'seo_item_condition' => $settings['seo_item_condition'] ?? config('seo.item_condition'),
                'seo_google_verification' => $settings['seo_google_verification'] ?? '',
                'seo_bing_verification' => $settings['seo_bing_verification'] ?? '',
            ],
            'flags' => [
                'seo_site_noindex' => ($settings['seo_site_noindex'] ?? '0') === '1',
                'seo_sitemap_include_out_of_stock' => ($settings['seo_sitemap_include_out_of_stock'] ?? '1') === '1',
            ],
            'settings' => $settings,
            'cardOptions' => [
                'summary_large_image' => 'Large Image',
                'summary' => 'Summary',
            ],
            'conditionOptions' => [
                'NewCondition' => 'New',
                'UsedCondition' => 'Used',
                'RefurbishedCondition' => 'Refurbished',
            ],
            'robotsUrl' => route('robots'),
            'sitemapUrl' => route('sitemap.index'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'seo_title_template' => ['required', 'string', 'max:120'],
            'seo_default_description' => ['nullable', 'string', 'max:320'],
            'seo_default_og_image' => ['nullable', 'url', 'max:500'],
            'seo_twitter_card' => ['required', Rule::in(['summary', 'summary_large_image'])],
            'seo_twitter_site' => ['nullable', 'string', 'max:64'],
            'seo_organization_name' => ['nullable', 'string', 'max:255'],
            'seo_organization_logo' => ['nullable', 'url', 'max:500'],
            'seo_organization_sameas' => ['nullable', 'string', 'max:2000'],
            'seo_item_condition' => ['required', Rule::in(['NewCondition', 'UsedCondition', 'RefurbishedCondition'])],
            'seo_google_verification' => ['nullable', 'string', 'max:255'],
            'seo_bing_verification' => ['nullable', 'string', 'max:255'],
        ]);

        foreach (self::TEXT_KEYS as $key) {
            Setting::put($key, (string) $request->input($key, ''));
        }

        foreach (self::BOOL_KEYS as $key) {
            Setting::put($key, $request->boolean($key) ? '1' : '0');
        }

        return back()->with(
            'status',
            $request->boolean('seo_site_noindex')
                ? 'SEO settings saved. Search engines are now discouraged from the whole site.'
                : 'SEO settings saved.'
        );
    }
}
