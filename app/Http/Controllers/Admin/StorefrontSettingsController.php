<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Store identity, catalog behaviour, and checkout policy. Everything is written
 * to the settings table and applied over config at boot (DB-driven config).
 */
class StorefrontSettingsController extends Controller
{
    /** Plain string settings this screen owns. */
    private const TEXT_KEYS = [
        'store_name', 'store_tagline', 'store_email', 'store_phone', 'store_address',
        'currency', 'currency_symbol', 'currency_decimals',
        'products_per_page', 'low_stock_threshold', 'order_prefix',
        'tax_mode', 'max_width',
    ];

    /** Toggle-switch settings, stored as '1'/'0'. */
    private const BOOL_KEYS = [
        'allow_backorder', 'hide_out_of_stock', 'guest_checkout', 'terms_required', 'tax_shipping',
    ];

    public function edit()
    {
        return view('admin.settings.storefront', [
            'settings' => Setting::map(),
            'widthOptions' => [
                'max-w-5xl' => 'Narrow',
                'max-w-6xl' => 'Standard',
                'max-w-7xl' => 'Wide',
                'max-w-screen-2xl' => 'Full',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'store_tagline' => ['nullable', 'string', 'max:255'],
            'store_email' => ['nullable', 'email', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:64'],
            'store_address' => ['nullable', 'string', 'max:500'],
            'currency' => ['required', 'string', 'size:3'],
            'currency_symbol' => ['required', 'string', 'max:4'],
            'currency_decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'products_per_page' => ['required', 'integer', 'min:1', 'max:96'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'order_prefix' => ['nullable', 'string', 'max:16'],
            'tax_mode' => ['required', Rule::in(['exclusive', 'inclusive'])],
            'max_width' => ['required', 'string', 'max:32'],
        ]);

        foreach (self::TEXT_KEYS as $key) {
            Setting::put($key, (string) $request->input($key, ''));
        }

        foreach (self::BOOL_KEYS as $key) {
            Setting::put($key, $request->boolean($key) ? '1' : '0');
        }

        return back()->with('status', 'Storefront settings saved.');
    }
}
