<?php

namespace App\Services\Calendar;

use App\Models\CalendarConnection;
use App\Services\Calendar\Contracts\CalendarProvider;
use App\Services\Calendar\Providers\AppleCalDavProvider;
use App\Services\Calendar\Providers\GoogleCalendarProvider;
use App\Services\Calendar\Providers\MicrosoftGraphProvider;
use App\Services\Calendar\Providers\NylasProvider;

/**
 * Resolves a CalendarProvider by key or for a connection, and lists the
 * providers a staff member may connect right now. The sync layer and the
 * availability free/busy computation are provider-neutral; this is the one place
 * that maps a provider key to its implementation. Mirrors PaymentGatewayManager.
 */
class CalendarManager
{
    /** @var array<string, class-string<CalendarProvider>> */
    private const PROVIDERS = [
        'google' => GoogleCalendarProvider::class,
        'microsoft' => MicrosoftGraphProvider::class,
        'apple' => AppleCalDavProvider::class,
        'nylas' => NylasProvider::class,
    ];

    /** @var array<string, CalendarProvider> */
    private array $resolved = [];

    public function get(string $key): ?CalendarProvider
    {
        if (! isset(self::PROVIDERS[$key])) {
            return null;
        }

        return $this->resolved[$key] ??= app(self::PROVIDERS[$key]);
    }

    /** The provider that owns a given connection. */
    public function for(CalendarConnection $c): ?CalendarProvider
    {
        return $this->get((string) $c->provider);
    }

    /**
     * Provider keys a staff member can connect right now (switched on AND their
     * app-level credentials are present).
     *
     * @return array<int, string>
     */
    public function enabledProviders(): array
    {
        $out = [];
        foreach (array_keys(self::PROVIDERS) as $key) {
            if (CalendarSettings::enabled($key)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys(self::PROVIDERS);
    }
}
