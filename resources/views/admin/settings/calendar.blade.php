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
                    @include('admin.settings.partials.provider-icon', ['provider' => $key])
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
                        <x-alert type="info" title="How To Get A Google Client ID And Secret">
                            <p>In the
                                <a href="https://console.cloud.google.com" target="_blank" rel="noopener"
                                   class="font-medium underline">Google Cloud Console</a>,
                                select (or create) a project, then:</p>
                            <ol class="mt-2 list-decimal space-y-1.5 pl-5">
                                <li><span class="font-medium">Enable the API</span> &mdash;
                                    <span class="font-medium">APIs &amp; Services</span> &rarr;
                                    <span class="font-medium">Library</span>, search
                                    <span class="font-medium">Google Calendar API</span> and click
                                    <span class="font-medium">Enable</span>.</li>
                                <li><span class="font-medium">Consent screen</span> &mdash;
                                    <span class="font-medium">APIs &amp; Services</span> &rarr;
                                    <span class="font-medium">OAuth consent screen</span>. Choose
                                    <span class="font-medium">External</span>, fill in app name + support email, and add the
                                    scopes <code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">calendar.events</code>
                                    and <code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">calendar.readonly</code>.
                                    While it stays in <span class="font-medium">Testing</span>, add each staff email as a test
                                    user (or <span class="font-medium">Publish</span> the app).</li>
                                <li><span class="font-medium">Create the client</span> &mdash;
                                    <span class="font-medium">Credentials</span> &rarr;
                                    <span class="font-medium">Create Credentials</span> &rarr;
                                    <span class="font-medium">OAuth client ID</span>, application type
                                    <span class="font-medium">Web application</span>.</li>
                                <li><span class="font-medium">Authorized redirect URI</span> &mdash; add the Redirect URI shown
                                    at the bottom of this page. It must match exactly.</li>
                                <li>Copy the generated <span class="font-medium">Client ID</span> and
                                    <span class="font-medium">Client secret</span> into the fields below.</li>
                            </ol>
                            <p class="mt-2">Save, then use <span class="font-medium">Test The Google Connection</span> below to
                                confirm the credentials before staff connect their calendars.</p>
                        </x-alert>

                        <x-toggle name="google_calendar_enabled" :checked="$checked('google_calendar_enabled')"
                                  label="Enable Google Calendar"
                                  description="Stays off until a client ID and client secret are saved." />

                        <x-field label="Client ID" for="google_client_id" :error="$errors->first('google_client_id')"
                                 hint="From a Google Cloud OAuth 2.0 Client (Web application).">
                            <x-input id="google_client_id" name="google_client_id" :value="$v('google_client_id')"
                                     autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" placeholder="xxxxxxxx.apps.googleusercontent.com" />
                        </x-field>

                        <x-field for="google_client_secret" :error="$errors->first('google_client_secret')"
                                 hint="Never shown again once saved. Leave blank to keep the current secret.">
                            <x-slot:label>
                                Client Secret
                                @if ($has_google_secret)<x-badge color="success" class="ml-2 align-middle">Configured</x-badge>@endif
                            </x-slot:label>
                            <x-input id="google_client_secret" name="google_client_secret" type="password" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other"
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
                        <x-alert type="info" title="How To Get A Microsoft Client ID And Secret">
                            <p>In the
                                <a href="https://entra.microsoft.com" target="_blank" rel="noopener"
                                   class="font-medium underline">Microsoft Entra admin center</a>
                                (or Azure Portal &rarr; <span class="font-medium">Microsoft Entra ID</span>):</p>
                            <ol class="mt-2 list-decimal space-y-1.5 pl-5">
                                <li><span class="font-medium">Register the app</span> &mdash;
                                    <span class="font-medium">App registrations</span> &rarr;
                                    <span class="font-medium">New registration</span>. Give it a name and, for
                                    <span class="font-medium">Supported account types</span>, pick
                                    <span class="font-medium">Accounts in any organizational directory and personal Microsoft
                                    accounts</span> (this matches the default <code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">common</code> tenant).</li>
                                <li><span class="font-medium">Redirect URI</span> &mdash; set platform
                                    <span class="font-medium">Web</span> and paste the Redirect URI shown at the bottom of this
                                    page. It must match exactly. Then <span class="font-medium">Register</span>.</li>
                                <li><span class="font-medium">Client ID</span> &mdash; from the app's
                                    <span class="font-medium">Overview</span>, copy the
                                    <span class="font-medium">Application (client) ID</span> into the field below. Leave Tenant as
                                    <code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">common</code> unless you want to
                                    restrict sign-in to one tenant (then use its Directory ID).</li>
                                <li><span class="font-medium">Client secret</span> &mdash;
                                    <span class="font-medium">Certificates &amp; secrets</span> &rarr;
                                    <span class="font-medium">New client secret</span>. Copy the secret
                                    <span class="font-medium">Value</span> immediately (not the Secret ID) into the field below.</li>
                                <li><span class="font-medium">Permissions</span> &mdash;
                                    <span class="font-medium">API permissions</span> &rarr;
                                    <span class="font-medium">Add a permission</span> &rarr;
                                    <span class="font-medium">Microsoft Graph</span> &rarr;
                                    <span class="font-medium">Delegated</span>, add
                                    <code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">Calendars.ReadWrite</code>
                                    (<code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">offline_access</code>,
                                    <code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">openid</code>,
                                    <code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">email</code> are included by sign-in).</li>
                            </ol>
                            <p class="mt-2">Save, then use <span class="font-medium">Test The Microsoft Connection</span> below to
                                confirm the tenant is reachable before staff connect their calendars.</p>
                        </x-alert>

                        <x-toggle name="microsoft_calendar_enabled" :checked="$checked('microsoft_calendar_enabled')"
                                  label="Enable Microsoft Outlook"
                                  description="Stays off until a client ID and client secret are saved." />

                        <x-field label="Application (Client) ID" for="microsoft_client_id" :error="$errors->first('microsoft_client_id')"
                                 hint="From an Azure AD App Registration.">
                            <x-input id="microsoft_client_id" name="microsoft_client_id" :value="$v('microsoft_client_id')" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" />
                        </x-field>

                        <x-field for="microsoft_client_secret" :error="$errors->first('microsoft_client_secret')"
                                 hint="A client secret VALUE (not its ID). Leave blank to keep the current secret.">
                            <x-slot:label>
                                Client Secret
                                @if ($has_microsoft_secret)<x-badge color="success" class="ml-2 align-middle">Configured</x-badge>@endif
                            </x-slot:label>
                            <x-input id="microsoft_client_secret" name="microsoft_client_secret" type="password" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other"
                                     :placeholder="$has_microsoft_secret ? 'Leave blank to keep the current secret' : 'Secret value'" />
                        </x-field>

                        <x-field label="Tenant" for="microsoft_tenant" :error="$errors->first('microsoft_tenant')"
                                 hint="Use 'common' for both personal and work/school accounts, or a specific tenant ID to restrict sign-in.">
                            <x-input id="microsoft_tenant" name="microsoft_tenant" :value="$v('microsoft_tenant', 'common')" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" placeholder="common" />
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
                        <x-alert type="info" title="Where To Find These In Nylas">
                            <p>Sign in to the Nylas v3 dashboard at
                                <a href="https://dashboard.nylas.com" target="_blank" rel="noopener"
                                   class="font-medium underline">dashboard.nylas.com</a>
                                and select (or create) your application, then:</p>
                            <ol class="mt-2 list-decimal space-y-1.5 pl-5">
                                <li><span class="font-medium">Client ID</span> &mdash; on the application's
                                    <span class="font-medium">Overview</span> page (also under
                                    <span class="font-medium">App Settings</span>). Copy it into the Client ID field below.</li>
                                <li><span class="font-medium">API Key</span> &mdash; left sidebar
                                    <span class="font-medium">API Keys</span> &rarr; <span class="font-medium">Create API key</span>.
                                    It starts with <code class="rounded bg-white/60 px-1 py-0.5 font-mono text-xs">nyk_</code>
                                    and is shown only once, so copy it straight into the API Key field below.</li>
                                <li><span class="font-medium">Data Region</span> &mdash; shown at the top of the dashboard
                                    (United States or Europe). It must match your application's region, or the key is rejected.</li>
                                <li><span class="font-medium">Callback URI</span> &mdash; left sidebar
                                    <span class="font-medium">Hosted Authentication</span> &rarr;
                                    <span class="font-medium">Callback URIs</span> &rarr; <span class="font-medium">Add</span>,
                                    and paste the Redirect URI shown at the bottom of this page. It must match exactly.</li>
                            </ol>
                            <p class="mt-2">Save, then use <span class="font-medium">Test The Nylas Connection</span> below to
                                confirm the key before staff connect their calendars.</p>
                        </x-alert>

                        <x-toggle name="nylas_enabled" :checked="$checked('nylas_enabled')"
                                  label="Enable Nylas"
                                  description="Stays off until an API key and client ID are saved." />

                        <x-field label="Client ID" for="nylas_client_id" :error="$errors->first('nylas_client_id')"
                                 hint="Your Nylas application's client ID (Nylas v3 dashboard).">
                            <x-input id="nylas_client_id" name="nylas_client_id" :value="$v('nylas_client_id')" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" />
                        </x-field>

                        <x-field for="nylas_api_key" :error="$errors->first('nylas_api_key')"
                                 hint="Never shown again once saved. Leave blank to keep the current key.">
                            <x-slot:label>
                                API Key
                                @if ($has_nylas_key)<x-badge color="success" class="ml-2 align-middle">Configured</x-badge>@endif
                            </x-slot:label>
                            <x-input id="nylas_api_key" name="nylas_api_key" type="password" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other"
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
