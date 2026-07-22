<x-layouts.app title="Calendar">
    @php
        $v = fn (string $key, $default = '') => old($key, $settings[$key] ?? $default);
        $checked = fn (string $key, bool $default = false) => old($key, ($settings[$key] ?? ($default ? '1' : '0')) === '1');

        $tabs = [
            'google' => 'Google',
            'microsoft' => 'Microsoft',
            'apple' => 'Apple',
            'nylas' => 'Nylas',
        ];
    @endphp

    <x-page-header title="Calendar" icon="clock" subtitle="Connect Google, Microsoft, Apple, or Nylas so work orders sync to staff calendars.">
        <x-slot:meta>
            @php $anyEnabled = collect($enabled)->contains(true); @endphp
            @if ($anyEnabled)
                <x-badge color="success" dot>Calendar Sync On</x-badge>
            @else
                <x-badge color="neutral" dot>Not Configured</x-badge>
            @endif
        </x-slot:meta>
    </x-page-header>

    @if ($errors->first('calendar'))
        <div class="mb-6">
            <x-alert type="danger" title="Calendar Test">{{ $errors->first('calendar') }}</x-alert>
        </div>
    @endif

    <x-alert type="info" title="How Calendar Connections Work">
        Enter each provider's app-level OAuth credentials here, then each staff member connects their own calendar on the
        My Calendar page. Apple uses an app-specific password entered per staff member and needs no app-level credentials.
        Secrets are write-only: leave a secret field blank to keep the value already saved.
    </x-alert>

    {{-- One shared Alpine scope so the settings form's tabs and the (separate,
         non-nested) test forms below always show the same provider. --}}
    <div x-data="{ tab: 'google' }" class="mt-6 space-y-4">
        <div class="flex items-center gap-1 overflow-x-auto rounded-xl bg-white p-1 ring-1 ring-slate-200">
            @foreach ($tabs as $key => $label)
                <button type="button" x-on:click="tab = @js($key)"
                        x-bind:class="tab === @js($key) ? 'bg-brand-50 text-brand-700 ring-1 ring-brand-100' : 'text-slate-500 hover:text-slate-700'"
                        class="inline-flex items-center gap-2 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition">
                    {{ $label }}
                    @if ($enabled[$key])<x-badge color="success">On</x-badge>@endif
                </button>
            @endforeach
        </div>

        <form method="POST" action="{{ route('settings.calendar.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Google ---------------------------------------------------- --}}
            <div x-show="tab === 'google'" x-cloak>
                <x-card title="Google Calendar" subtitle="OAuth 2.0 client from Google Cloud. Events sync to each staff member's primary calendar.">
                    <div class="space-y-5">
                        <x-toggle name="google_calendar_enabled" :checked="$checked('google_calendar_enabled')"
                                  label="Enable Google Calendar"
                                  description="Stays off until a client ID and client secret are saved." />

                        <x-field label="Client ID" for="google_client_id" :error="$errors->first('google_client_id')"
                                 hint="From a Google Cloud OAuth 2.0 Client (Web application).">
                            <x-input id="google_client_id" name="google_client_id" :value="$v('google_client_id')"
                                     autocomplete="off" placeholder="xxxxxxxx.apps.googleusercontent.com" />
                        </x-field>

                        <x-field for="google_client_secret" :error="$errors->first('google_client_secret')"
                                 hint="Never shown again once saved. Leave blank to keep the current secret.">
                            <x-slot:label>
                                Client Secret
                                @if ($has_google_secret)<x-badge color="success" class="ml-2 align-middle">Configured</x-badge>@endif
                            </x-slot:label>
                            <x-input id="google_client_secret" name="google_client_secret" type="password" autocomplete="new-password"
                                     :placeholder="$has_google_secret ? 'Leave blank to keep the current secret' : 'GOCSPX-...'" />
                        </x-field>

                        @include('admin.settings.partials.redirect-uri', ['uri' => $redirectUris['google']])
                    </div>
                </x-card>
            </div>

            {{-- Microsoft ------------------------------------------------- --}}
            <div x-show="tab === 'microsoft'" x-cloak>
                <x-card title="Microsoft Outlook" subtitle="Azure AD App Registration + Microsoft Graph. Events sync to each staff member's Outlook calendar.">
                    <div class="space-y-5">
                        <x-toggle name="microsoft_calendar_enabled" :checked="$checked('microsoft_calendar_enabled')"
                                  label="Enable Microsoft Outlook"
                                  description="Stays off until a client ID and client secret are saved." />

                        <x-field label="Application (Client) ID" for="microsoft_client_id" :error="$errors->first('microsoft_client_id')"
                                 hint="From an Azure AD App Registration.">
                            <x-input id="microsoft_client_id" name="microsoft_client_id" :value="$v('microsoft_client_id')" autocomplete="off" />
                        </x-field>

                        <x-field for="microsoft_client_secret" :error="$errors->first('microsoft_client_secret')"
                                 hint="A client secret VALUE (not its ID). Leave blank to keep the current secret.">
                            <x-slot:label>
                                Client Secret
                                @if ($has_microsoft_secret)<x-badge color="success" class="ml-2 align-middle">Configured</x-badge>@endif
                            </x-slot:label>
                            <x-input id="microsoft_client_secret" name="microsoft_client_secret" type="password" autocomplete="new-password"
                                     :placeholder="$has_microsoft_secret ? 'Leave blank to keep the current secret' : 'Secret value'" />
                        </x-field>

                        <x-field label="Tenant" for="microsoft_tenant" :error="$errors->first('microsoft_tenant')"
                                 hint="Use 'common' for both personal and work/school accounts, or a specific tenant ID to restrict sign-in.">
                            <x-input id="microsoft_tenant" name="microsoft_tenant" :value="$v('microsoft_tenant', 'common')" autocomplete="off" placeholder="common" />
                        </x-field>

                        @include('admin.settings.partials.redirect-uri', ['uri' => $redirectUris['microsoft']])
                    </div>
                </x-card>
            </div>

            {{-- Apple ----------------------------------------------------- --}}
            <div x-show="tab === 'apple'" x-cloak>
                <x-card title="Apple iCloud" subtitle="CalDAV. No OAuth app to configure: each staff member connects with their Apple ID.">
                    <div class="space-y-5">
                        <x-toggle name="apple_calendar_enabled" :checked="$checked('apple_calendar_enabled')"
                                  label="Enable Apple iCloud"
                                  description="No app-level credentials needed. Each staff member connects with their Apple ID." />

                        <x-alert type="info" title="Apple Uses App-Specific Passwords">
                            Apple iCloud has no OAuth app to configure. Each staff member enters their Apple ID and an
                            app-specific password (from appleid.apple.com, under Sign-In and Security) on the My Calendar
                            page. Turn this on to let them do so.
                        </x-alert>
                    </div>
                </x-card>
            </div>

            {{-- Nylas ----------------------------------------------------- --}}
            <div x-show="tab === 'nylas'" x-cloak>
                <x-card title="Nylas" subtitle="One integration for Google, Microsoft, iCloud and more, via Nylas v3 hosted auth.">
                    <div class="space-y-5">
                        <x-toggle name="nylas_enabled" :checked="$checked('nylas_enabled')"
                                  label="Enable Nylas"
                                  description="Stays off until an API key and client ID are saved." />

                        <x-field label="Client ID" for="nylas_client_id" :error="$errors->first('nylas_client_id')"
                                 hint="Your Nylas application's client ID (Nylas v3 dashboard).">
                            <x-input id="nylas_client_id" name="nylas_client_id" :value="$v('nylas_client_id')" autocomplete="off" />
                        </x-field>

                        <x-field for="nylas_api_key" :error="$errors->first('nylas_api_key')"
                                 hint="Never shown again once saved. Leave blank to keep the current key.">
                            <x-slot:label>
                                API Key
                                @if ($has_nylas_key)<x-badge color="success" class="ml-2 align-middle">Configured</x-badge>@endif
                            </x-slot:label>
                            <x-input id="nylas_api_key" name="nylas_api_key" type="password" autocomplete="new-password"
                                     :placeholder="$has_nylas_key ? 'Leave blank to keep the current key' : 'nyk_...'" />
                        </x-field>

                        <x-field label="Data Region" for="nylas_api_region" required :error="$errors->first('nylas_api_region')"
                                 hint="Must match the region your Nylas application was created in.">
                            <x-select id="nylas_api_region" name="nylas_api_region">
                                <option value="us" @selected($v('nylas_api_region', 'us') === 'us')>United States (api.us.nylas.com)</option>
                                <option value="eu" @selected($v('nylas_api_region', 'us') === 'eu')>Europe (api.eu.nylas.com)</option>
                            </x-select>
                        </x-field>

                        @include('admin.settings.partials.redirect-uri', ['uri' => $redirectUris['nylas']])
                    </div>
                </x-card>
            </div>

            <div class="flex justify-end gap-3">
                <x-button type="submit" icon="check">Save Settings</x-button>
            </div>
        </form>

        {{-- Test connection. A SEPARATE form per provider (nesting inside the
             settings form would submit both). Shown for the active tab only. --}}
        @foreach ($tabs as $key => $label)
            <form method="POST" action="{{ route('settings.calendar.test', ['provider' => $key]) }}"
                  x-show="tab === @js($key)" x-cloak
                  class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-inset ring-slate-200">
                @csrf
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900">Test The {{ $label }} Connection</p>
                    <p class="text-sm text-slate-600">
                        @if ($key === 'apple')
                            Confirms iCloud CalDAV is reachable. Staff supply their own Apple ID when they connect.
                        @else
                            Calls {{ $label }} with the saved credentials and confirms they are accepted.
                        @endif
                    </p>
                </div>
                <x-button type="submit" variant="secondary" size="sm" icon="bolt">Test {{ $label }}</x-button>
            </form>
        @endforeach
    </div>
</x-layouts.app>
