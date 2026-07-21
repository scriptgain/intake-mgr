<x-layouts.app title="Spam Protection">
    @php
        // Presentation helpers only (values already resolved by the controller).
        $activeLabel = $providers[$activeProvider]['label'] ?? 'None';
    @endphp

    <x-page-header title="Spam Protection" icon="shield" subtitle="Choose a challenge provider and decide which forms it guards.">
        <x-slot:meta>
            <x-badge color="info" dot>Active: {{ $activeLabel }}</x-badge>
            @if ($honeypotEnabled)
                <x-badge color="success" dot>Honeypot On</x-badge>
            @else
                <x-badge color="warn" dot>Honeypot Off</x-badge>
            @endif
        </x-slot:meta>
    </x-page-header>

    <div class="mb-6">
        <x-alert type="info" title="Always-On Baseline">
            A hidden honeypot field and a minimum-time-to-submit check run on every protected form, independent of the
            provider below, so a form is never left unprotected and never breaks because a key is missing. The provider
            you pick is an extra layer on top.
        </x-alert>
    </div>

    <form method="POST" action="{{ route('settings.spam.update') }}"
          x-data="{ tab: 'provider', provider: @js(old('captcha_provider', $activeProvider)) }" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Tab strip (Allen prefers tabs over one long scroll). --}}
        <div class="flex items-center gap-1 border-b border-slate-200">
            <button type="button" x-on:click="tab = 'provider'"
                    x-bind:class="tab === 'provider' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition">
                <x-icon name="shield" class="h-4 w-4 shrink-0" /> Provider
            </button>
            <button type="button" x-on:click="tab = 'surfaces'"
                    x-bind:class="tab === 'surfaces' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition">
                <x-icon name="bag" class="h-4 w-4 shrink-0" /> Protected Forms
            </button>
            <button type="button" x-on:click="tab = 'policy'"
                    x-bind:class="tab === 'policy' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition">
                <x-icon name="settings" class="h-4 w-4 shrink-0" /> Baseline &amp; Policy
            </button>
        </div>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Tab: Provider                                                    --}}
        {{-- ---------------------------------------------------------------- --}}
        <div x-show="tab === 'provider'" x-cloak class="space-y-6">
            <x-card title="Challenge Provider" subtitle="Only the selected provider's widget and keys are used.">
                <div class="space-y-3">
                    @foreach ($providers as $key => $meta)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg p-3 ring-1 ring-inset transition"
                               x-bind:class="provider === @js($key) ? 'bg-brand-50 ring-brand-300' : 'bg-white ring-slate-200 hover:ring-slate-300'">
                            <input type="radio" name="captcha_provider" value="{{ $key }}" x-model="provider"
                                   class="mt-1 h-4 w-4 shrink-0 border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-900">{{ $meta['label'] }}</span>
                                    @if ($meta['third_party'])
                                        @if ($meta['configured'])
                                            <x-badge color="success">Keys Set</x-badge>
                                        @else
                                            <x-badge color="warn">Keys Needed</x-badge>
                                        @endif
                                    @endif
                                </span>
                                <span class="mt-0.5 block text-sm text-slate-500">{{ $meta['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-card>

            {{-- Per-provider key fields. Each block shows only when its provider
                 is the selected one, so keys never clutter the screen. --}}
            @php
                $keyBlocks = [
                    'recaptcha_v2' => ['Google reCAPTCHA v2', 'pk / secret from the reCAPTCHA admin console.'],
                    'recaptcha_v3' => ['Google reCAPTCHA v3', 'Site + secret from the reCAPTCHA admin console.'],
                    'hcaptcha' => ['hCaptcha', 'Site key + secret from your hCaptcha dashboard.'],
                    'turnstile' => ['Cloudflare Turnstile', 'Site key + secret from the Cloudflare Turnstile dashboard.'],
                ];
            @endphp
            @foreach ($keyBlocks as $pkey => [$blockTitle, $blockSub])
                <div x-show="provider === @js($pkey)" x-cloak>
                    <x-card :title="$blockTitle . ' Keys'" :subtitle="$blockSub">
                        <div class="space-y-5">
                            <x-field label="Site Key" for="{{ $credentialKeys[$pkey]['site'] }}"
                                     hint="Public by design. Rendered into the widget.">
                                <x-input id="{{ $credentialKeys[$pkey]['site'] }}" name="{{ $credentialKeys[$pkey]['site'] }}"
                                         :value="old($credentialKeys[$pkey]['site'], $siteKeys[$pkey])" autocomplete="off" />
                            </x-field>

                            <x-field for="{{ $credentialKeys[$pkey]['secret'] }}" hint="Never shown again once saved. Leave blank to keep the current key.">
                                <x-slot:label>
                                    Secret Key
                                    @if ($stored[$pkey])<x-badge color="success" class="ml-2 align-middle">Configured</x-badge>@endif
                                </x-slot:label>
                                <x-input id="{{ $credentialKeys[$pkey]['secret'] }}" name="{{ $credentialKeys[$pkey]['secret'] }}"
                                         type="password" autocomplete="off"
                                         :placeholder="$stored[$pkey] ? 'Leave blank to keep the current secret' : 'Paste the secret key'" />
                            </x-field>

                            @if ($pkey === 'recaptcha_v3')
                                <x-field label="Score Threshold" for="captcha_recaptcha_v3_threshold"
                                         hint="reCAPTCHA v3 scores each request 0.0 (bot) to 1.0 (human). Submissions below this score are rejected. 0.5 is a sensible start.">
                                    <x-input id="captcha_recaptcha_v3_threshold" name="captcha_recaptcha_v3_threshold" type="number"
                                             step="0.1" min="0" max="1" :value="old('captcha_recaptcha_v3_threshold', $v3Threshold)" />
                                </x-field>
                            @endif

                            <x-alert type="info">
                                Seeded with the provider's official public TEST keys so you can demo it immediately. Test
                                keys always pass and take no real signal. Replace them with your own keys before go-live.
                            </x-alert>
                        </div>
                    </x-card>
                </div>
            @endforeach

            <div x-show="provider === 'builtin'" x-cloak>
                <x-alert type="success" title="No Keys Needed">
                    The built-in challenge is signed on this server and works with no third-party account. It is the
                    default so protection works out of the box.
                </x-alert>
            </div>
        </div>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Tab: Protected Forms                                             --}}
        {{-- ---------------------------------------------------------------- --}}
        <div x-show="tab === 'surfaces'" x-cloak class="space-y-6">
            <x-card title="Protected Forms" subtitle="Each surface can require the provider challenge independently.">
                <div class="space-y-5">
                    @foreach ($surfaces as $skey => [$sLabel, $sDesc, $sOn])
                        <x-toggle name="captcha_on_{{ $skey }}" :checked="old('captcha_on_'.$skey, $sOn)"
                                  :label="$sLabel" :description="$sDesc" />
                    @endforeach
                </div>
            </x-card>

            <x-alert type="warn" title="About Guest Checkout">
                A challenge on checkout is the single most likely place to cost a real order, so it is OFF by default.
                Turn it on only if checkout spam becomes a genuine problem. The honeypot and time-trap baseline still run
                on checkout regardless, and they are invisible to shoppers.
            </x-alert>
        </div>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Tab: Baseline & Policy                                           --}}
        {{-- ---------------------------------------------------------------- --}}
        <div x-show="tab === 'policy'" x-cloak class="space-y-6">
            <x-card title="Honeypot &amp; Time-Trap" subtitle="The always-on baseline. Independent of the provider.">
                <div class="space-y-5">
                    <x-toggle name="captcha_honeypot_enabled" :checked="old('captcha_honeypot_enabled', $honeypotEnabled)"
                              label="Enable Honeypot &amp; Time-Trap"
                              description="A hidden field bots fill, plus a minimum time before a form can be submitted. Leave this on." />
                    <x-field label="Minimum Seconds To Submit" for="captcha_min_seconds"
                             hint="A form submitted faster than this many seconds is treated as automated. 2 is safe for humans; set 0 to disable only the timing check.">
                        <x-input id="captcha_min_seconds" name="captcha_min_seconds" type="number" min="0" max="120"
                                 :value="old('captcha_min_seconds', $minSeconds)" class="max-w-[10rem]" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Fail Policy" subtitle="What happens when a third-party provider cannot be reached.">
                <div class="space-y-5">
                    <x-field label="When The Provider Is Unreachable" for="captcha_fail_policy"
                             hint="Applies only to an outage of the provider's servers, not to a submission the provider rejects. A rejected submission is always blocked.">
                        <x-select id="captcha_fail_policy" name="captcha_fail_policy">
                            <option value="closed" @selected(old('captcha_fail_policy', $failPolicy) === 'closed')>Fail Closed — block the submission (recommended for logins)</option>
                            <option value="open" @selected(old('captcha_fail_policy', $failPolicy) === 'open')>Fail Open — allow the submission through</option>
                        </x-select>
                    </x-field>

                    <x-toggle name="captcha_contact_fail_open" :checked="old('captcha_contact_fail_open', $contactFailOpen)"
                              label="Contact / Newsletter Forms Fail Open"
                              description="A genuine enquiry should not be lost to a captcha outage. On by default; overrides the policy above for contact forms only. An audit-log note records each time this is used." />
                </div>
            </x-card>
        </div>

        <div class="flex justify-end gap-3">
            <x-button type="submit" icon="check">Save Settings</x-button>
        </div>
    </form>

    {{-- Separate form: a real round-trip test of the active configuration. --}}
    <form method="POST" action="{{ route('settings.spam.test') }}"
          class="mt-4 flex items-center justify-between gap-4 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-inset ring-slate-200">
        @csrf
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-900">Test This Configuration</p>
            <p class="text-sm text-slate-600">Runs a live round-trip for the active provider ({{ $activeLabel }}) and reports the result.</p>
        </div>
        <x-button type="submit" variant="secondary" size="sm" icon="bolt">Run Test</x-button>
    </form>
</x-layouts.app>
