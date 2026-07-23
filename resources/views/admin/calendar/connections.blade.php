<x-layouts.app title="My Calendar">
    <x-page-header
        eyebrow="Scheduling"
        title="My Calendar"
        icon="refresh"
        subtitle="Connect your calendar so your scheduled work orders appear on it automatically.">
        <x-slot:actions>
            <form method="POST" action="{{ route('calendar.sync') }}">
                @csrf
                <x-button type="submit" variant="secondary" icon="refresh">Sync Now</x-button>
            </form>
        </x-slot:actions>
    </x-page-header>

    @php
        $meta = [
            'google' => ['icon' => 'globe', 'blurb' => 'Two-way sync with Google Calendar.'],
            'microsoft' => ['icon' => 'globe', 'blurb' => 'Two-way sync with Outlook / Microsoft 365.'],
            'apple' => ['icon' => 'globe', 'blurb' => 'Sync with iCloud using an app-specific password.'],
            'nylas' => ['icon' => 'bolt', 'blurb' => 'Unified sync through your Nylas account.'],
        ];
    @endphp

    @if (empty($enabledProviders))
        <x-card>
            <x-empty-state icon="clock" title="No Calendar Providers Enabled"
                           description="An administrator needs to enable and configure a provider first.">
                <x-slot:action>
                    <x-button href="{{ route('settings.calendar.edit') }}" icon="settings">Open Calendar Settings</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($enabledProviders as $provider)
                @php($conn = $connections[$provider] ?? null)
                <x-card>
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                            <x-icon :name="$meta[$provider]['icon'] ?? 'globe'" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-slate-900">{{ $providerLabels[$provider] ?? ucfirst($provider) }}</h3>
                                @if ($conn && $conn->status === 'connected')
                                    <x-badge color="success" dot>Connected</x-badge>
                                @elseif ($conn && $conn->status === 'error')
                                    <x-badge color="danger" dot>Error</x-badge>
                                @endif
                            </div>
                            <p class="mt-0.5 text-sm text-slate-500">{{ $meta[$provider]['blurb'] ?? '' }}</p>

                            @if ($conn && $conn->status === 'connected')
                                <p class="mt-3 text-sm text-slate-600">{{ $conn->account_email ?: 'Connected' }}</p>
                                @if ($conn->last_synced_at)<p class="text-xs text-slate-400">Last synced {{ $conn->last_synced_at->diffForHumans() }}</p>@endif
                                <div class="mt-4">
                                    <x-delete-button :action="route('calendar.disconnect', $conn)"
                                        name="disconnect-{{ $provider }}" label="Disconnect"
                                        title="Disconnect This Calendar?"
                                        message="Events this app added will be removed from the calendar. Your work orders are unaffected." />
                                </div>
                            @elseif ($provider === 'apple')
                                <div x-data="{ open: {{ $errors->has('apple_email') ? 'true' : 'false' }} }" class="mt-4">
                                    <x-button type="button" size="sm" icon="plus" x-on:click="open = !open">Connect iCloud</x-button>
                                    <form x-show="open" x-cloak method="POST" action="{{ route('calendar.apple') }}" class="mt-4 space-y-3" autocomplete="off">
                                        @csrf
                                        {{-- Decoys: password managers autofill the FIRST matching username/password
                                             fields, so these off-screen ones absorb the fill before the real inputs. --}}
                                        <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden">
                                            <input type="text" name="_pm_decoy_user" tabindex="-1" autocomplete="username">
                                            <input type="password" name="_pm_decoy_pass" tabindex="-1" autocomplete="new-password">
                                        </div>
                                        <x-field label="Apple ID Email" for="apple_email" :error="$errors->first('apple_email')">
                                            <x-input id="apple_email" name="apple_email" type="email" autocomplete="off"
                                                     data-lpignore="true" data-1p-ignore data-form-type="other" :value="old('apple_email')" />
                                        </x-field>
                                        <x-field label="App-Specific Password" for="app_password"
                                                 hint="Create one at appleid.apple.com → Sign-In and Security → App-Specific Passwords.">
                                            <x-input id="app_password" name="app_password" type="password" autocomplete="new-password"
                                                     data-lpignore="true" data-1p-ignore data-form-type="other" />
                                        </x-field>
                                        <x-button type="submit" size="sm" icon="check">Connect</x-button>
                                    </form>
                                </div>
                            @else
                                <div class="mt-4">
                                    <x-button href="{{ route('calendar.connect', $provider) }}" size="sm" icon="plus">Connect</x-button>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <div class="section-divider my-8"></div>

    <x-card title="Subscription Feed"
            subtitle="Subscribe to this private URL in any calendar app for a live, read-only view of your scheduled work orders.">
        <div x-data="{ copied: false, url: @js($feedUrl) }" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <input type="text" readonly :value="url" x-ref="feed"
                   class="min-w-0 flex-1 rounded-lg border-0 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 ring-1 ring-inset ring-slate-200">
            <x-button type="button" variant="secondary" size="sm" icon="copy"
                      x-on:click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 1500)">
                <span x-text="copied ? 'Copied' : 'Copy URL'"></span>
            </x-button>
        </div>
        <p class="mt-3 text-xs text-slate-500">Keep this URL private. Anyone with it can see your schedule. In Apple Calendar use File → New Calendar Subscription; in Google use Other calendars → From URL.</p>
    </x-card>
</x-layouts.app>
