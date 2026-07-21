<x-layouts.app title="Settings">
    <x-page-header title="Settings" icon="settings" subtitle="Configure the store, the panel, and this server." />

    @php
        // Presentation-only: the tile list for this index. Each row is
        // [label, description, icon, route name, admin-only]. Rendered only when
        // the route exists, so one list serves every build of the panel.
        $groups = [
            'Store' => [
                ['Storefront', 'Store identity, catalog behaviour, checkout policy, and tax mode.', 'bag', 'settings.storefront.edit', false],
                ['Payments', 'Gateways and their credentials.', 'credit-card', 'settings.payments.edit', false],
                ['SEO', 'Indexing, titles, link previews, sitemap, and robots.', 'globe', 'settings.seo.edit', false],
                ['Spam Protection', 'Anti-spam provider, honeypot, and per-form challenges.', 'shield', 'settings.spam.edit', false],
                ['Shipping', 'Zones and the rates offered inside them.', 'truck', 'shipping.index', false],
                ['Tax', 'Regional tax rules applied at checkout.', 'percent', 'taxes.index', false],
            ],
            'Appearance' => [
                ['Themes', 'Colour, typography, corners and spacing for the storefront.', 'star', 'themes.index', false],
                ['Templates', 'Edit the real Blade behind any page. Validated before it goes live.', 'edit', 'templates.index', true],
            ],
            'Panel' => [
                ['General', 'Regional formatting, table density, sessions, housekeeping.', 'settings', 'settings.general.edit', false],
                ['Branding', 'Product name, tagline, and accent colour.', 'edit', 'settings.branding.edit', false],
                ['Notifications', 'Where order and system alerts are sent.', 'bell', 'settings.notifications.edit', false],
                ['Integrations', 'Outbound webhooks and chat notifications.', 'bolt', 'settings.integrations.edit', false],
            ],
            'Security' => [
                ['Password', 'Change your own password.', 'lock', 'settings.password.edit', false],
                ['Two-Factor', 'Enrol an authenticator app on your account.', 'shield', 'settings.2fa.show', false],
                ['API Tokens', 'Bearer tokens for the REST API.', 'key', 'settings.tokens.index', false],
                ['Firewall', 'IP bans, allowlist, and active sessions.', 'shield', 'settings.firewall.index', true],
                ['Users & Admins', 'Staff accounts and their roles.', 'users', 'settings.users.index', true],
                ['Audit Log', 'Who changed what, and when.', 'book', 'settings.audit.index', true],
            ],
            'System' => [
                ['License', 'Your ScriptGain license key and its status.', 'shield', 'settings.license.edit', false],
                ['Updates', 'Check for and apply a new signed release.', 'download', 'settings.updates.show', false],
                ['Backup & Restore', 'Export or restore this panel\'s database and config.', 'archive', 'settings.backup.index', false],
                ['Host & SSL', 'Hostname and certificate management.', 'globe', 'settings.host.edit', true],
            ],
        ];
        $isAdmin = auth()->user()?->isAdmin() ?? false;
    @endphp

    <div class="space-y-8">
        @foreach ($groups as $groupTitle => $tiles)
            @php
                $visible = array_values(array_filter(
                    $tiles,
                    fn ($t) => \Illuminate\Support\Facades\Route::has($t[3]) && (! $t[4] || $isAdmin)
                ));
            @endphp
            @if (count($visible))
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">{{ $groupTitle }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($visible as [$label, $description, $icon, $routeName, $adminOnly])
                            <a href="{{ route($routeName) }}"
                               class="group flex items-start gap-3.5 rounded-xl bg-white p-5 ring-1 ring-slate-200 shadow-sm transition hover:ring-brand-300 hover:shadow">
                                {{-- Light-bg icon chip gets a border (house style). --}}
                                <span class="inline-flex items-center justify-center w-10 h-10 shrink-0 rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-100">
                                    <x-icon :name="$icon" class="w-5 h-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900 group-hover:text-brand-700">{{ $label }}</span>
                                    <span class="mt-0.5 block text-sm text-slate-500">{{ $description }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
                @unless ($loop->last)
                    <div class="section-divider"></div>
                @endunless
            @endif
        @endforeach
    </div>
</x-layouts.app>
