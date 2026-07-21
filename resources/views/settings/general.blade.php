<x-layouts.app title="General">
    <x-page-header title="General" icon="settings" subtitle="Regional formatting, table density, session security, and housekeeping." />

    <form method="POST" action="{{ route('settings.general.update') }}" x-data="{ tab: 'regional' }">
        @csrf
        @method('PUT')

        {{-- Tabs, so this screen never becomes one long scroll. --}}
        <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 mb-6">
            @foreach ([
                'regional' => ['Regional & Display', 'globe'],
                'security' => ['Security', 'lock'],
                'housekeeping' => ['Housekeeping', 'refresh'],
                'system' => ['System Info', 'server'],
            ] as $key => [$label, $icon])
                <button type="button" x-on:click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}'
                        ? 'border-brand-600 text-brand-700'
                        : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'"
                    class="inline-flex items-center gap-2 px-3.5 py-2.5 -mb-px border-b-2 text-sm font-medium transition">
                    <x-icon name="{{ $icon }}" class="w-4 h-4 shrink-0" />
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Regional & display --}}
        <div x-show="tab === 'regional'" class="space-y-6">
            <x-card title="Regional" subtitle="How dates, times, and numbers are shown throughout the panel.">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Timezone" for="timezone" :error="$errors->first('timezone')"
                        hint="Order timestamps and scheduled tasks use this zone.">
                        <x-select id="timezone" name="timezone">
                            @foreach ($timezones as $tz)
                                <option value="{{ $tz }}" @selected($v['timezone'] === $tz)>{{ $tz }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Week Starts On" for="week_starts_on" :error="$errors->first('week_starts_on')">
                        <x-select id="week_starts_on" name="week_starts_on">
                            <option value="sunday" @selected($v['week_starts_on'] === 'sunday')>Sunday</option>
                            <option value="monday" @selected($v['week_starts_on'] === 'monday')>Monday</option>
                        </x-select>
                    </x-field>

                    <x-field label="Date Format" for="date_format" :error="$errors->first('date_format')"
                        hint="PHP date() format. Currently: {{ $now->format($v['date_format']) }}">
                        <x-input id="date_format" name="date_format" value="{{ $v['date_format'] }}" />
                    </x-field>

                    <x-field label="Time Format" for="time_format" :error="$errors->first('time_format')"
                        hint="PHP date() format. Currently: {{ $now->format($v['time_format']) }}">
                        <x-input id="time_format" name="time_format" value="{{ $v['time_format'] }}" />
                    </x-field>
                </div>
            </x-card>

            <div class="section-divider"></div>

            <x-card title="Tables" subtitle="Density of the paginated lists in the admin.">
                <x-field label="Rows Per Page" for="rows_per_page" :error="$errors->first('rows_per_page')"
                    hint="Applies to Products, Orders, Customers, and Discounts.">
                    <x-input type="number" id="rows_per_page" name="rows_per_page" min="10" max="200" value="{{ $v['rows_per_page'] }}" />
                </x-field>
            </x-card>
        </div>

        {{-- Security --}}
        <div x-show="tab === 'security'" x-cloak class="space-y-6">
            <x-card title="Sessions & Access" subtitle="Applies to staff signing into this admin, not to storefront customers.">
                <div class="mb-5">
                    <x-toggle name="require_2fa" :checked="$v['require_2fa'] === '1'"
                        label="Require Two-Factor Authentication"
                        description="Every staff account must enrol an authenticator app before reaching the admin." />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 border-t border-slate-100 pt-5">
                    <x-field label="Idle Session Timeout" for="session_timeout_minutes" :error="$errors->first('session_timeout_minutes')"
                        hint="Minutes of inactivity before a staff session ends.">
                        <x-input type="number" id="session_timeout_minutes" name="session_timeout_minutes" min="5" max="43200" value="{{ $v['session_timeout_minutes'] }}" />
                    </x-field>

                    <x-field label="Force Password Change" for="force_password_days" :error="$errors->first('force_password_days')"
                        hint="Days before a password is considered stale. 0 = never.">
                        <x-input type="number" id="force_password_days" name="force_password_days" min="0" max="3650" value="{{ $v['force_password_days'] }}" />
                    </x-field>
                </div>
            </x-card>
        </div>

        {{-- Housekeeping --}}
        <div x-show="tab === 'housekeeping'" x-cloak class="space-y-6">
            <x-card title="Retention" subtitle="What the nightly shop:housekeeping task prunes.">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Keep Audit Log" for="audit_log_days" :error="$errors->first('audit_log_days')"
                        hint="Days of audit entries. 0 = forever.">
                        <x-input type="number" id="audit_log_days" name="audit_log_days" min="0" max="3650" value="{{ $v['audit_log_days'] }}" />
                    </x-field>

                    <x-field label="Abandoned Cart Lifetime" for="cart_expiry_days" :error="$errors->first('cart_expiry_days')"
                        hint="Days an untouched cart is kept before it is pruned.">
                        <x-input type="number" id="cart_expiry_days" name="cart_expiry_days" min="1" max="365" value="{{ $v['cart_expiry_days'] }}" />
                    </x-field>
                </div>
            </x-card>
        </div>

        {{-- System info --}}
        <div x-show="tab === 'system'" x-cloak class="space-y-6">
            <x-card title="System Information" subtitle="What this install is running." flush>
                <x-table flush>
                    <tbody>
                        @foreach ($info as $label => $value)
                            <tr>
                                <td class="font-medium text-slate-900 w-1/3">{{ $label }}</td>
                                <td class="tabular">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            </x-card>
        </div>

        <div class="section-divider mt-8 pt-5 flex items-center justify-end gap-2">
            <x-button type="submit" icon="check">Save Settings</x-button>
        </div>
    </form>
</x-layouts.app>
