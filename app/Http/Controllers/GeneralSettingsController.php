<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Panel-wide preferences: regional formatting, table density, session security,
 * and housekeeping retention. Store-specific settings (currency, catalog,
 * checkout, tax) live on the Storefront screen instead.
 */
class GeneralSettingsController extends Controller
{
    /** Defaults for every General setting. Keys are the Setting table keys. */
    public static function defaults(): array
    {
        return [
            // Regional & display
            'timezone' => config('app.timezone', 'UTC'),
            'date_format' => 'M j, Y',
            'time_format' => 'g:i A',
            'week_starts_on' => 'sunday',
            'rows_per_page' => '25',
            // Housekeeping
            'audit_log_days' => '180',
            'cart_expiry_days' => '30',
            // Security
            'session_timeout_minutes' => '120',
            'require_2fa' => '0',
            'force_password_days' => '0',
        ];
    }

    public function edit()
    {
        $map = Setting::map();
        $v = [];
        foreach (static::defaults() as $key => $default) {
            $v[$key] = $map[$key] ?? $default;
        }

        return view('settings.general', [
            'v' => $v,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'now' => now(),
            'info' => [
                'Product' => config('brand.name'),
                'App Version' => \App\Services\UpdateService::currentVersion(),
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Database' => config('database.default'),
                'Environment' => app()->environment(),
                'Server Time' => now()->format('D, M j Y g:i A T'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'date_format' => ['required', 'string', 'max:20'],
            'time_format' => ['required', 'string', 'max:20'],
            'week_starts_on' => ['required', Rule::in(['sunday', 'monday'])],
            'rows_per_page' => ['required', 'integer', 'min:10', 'max:200'],
            'audit_log_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'cart_expiry_days' => ['required', 'integer', 'min:1', 'max:365'],
            'session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:43200'],
            'force_password_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        // Toggles submit "0"/"1" via a hidden input; normalize explicitly.
        foreach (['require_2fa'] as $t) {
            $data[$t] = $request->boolean($t) ? '1' : '0';
        }

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }

        AuditLog::record('updated', 'General settings updated');

        return back()->with('status', 'General settings saved.');
    }
}
