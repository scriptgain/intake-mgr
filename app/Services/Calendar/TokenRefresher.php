<?php

namespace App\Services\Calendar;

use App\Models\CalendarConnection;

/**
 * Keeps a connection's access token usable before any provider API call.
 *
 * The sync layer calls ensureFresh() up front: if the token is within its
 * expiry skew it asks the provider to refresh (and persist) a new one. A
 * connection whose refresh fails is marked errored so the UI can prompt the
 * staff member to reconnect, and false is returned so the caller skips the call
 * rather than firing it with a dead token.
 */
class TokenRefresher
{
    public function __construct(private CalendarManager $manager) {}

    /**
     * Ensure the connection holds a usable access token. Returns whether an API
     * call may now be attempted against it.
     */
    public function ensureFresh(CalendarConnection $c): bool
    {
        $provider = $this->manager->for($c);

        if (! $provider) {
            return false;
        }

        // Not expired: the token (or, for CalDAV/Nylas, the standing credential)
        // is already usable.
        if (! $c->isExpired()) {
            return true;
        }

        if ($provider->refresh($c)) {
            return true;
        }

        // Refresh failed: record it once so the connection surfaces as needing
        // reconnection rather than silently failing every sync.
        if ($c->status !== 'error') {
            $c->forceFill([
                'status' => 'error',
                'last_error' => 'The calendar token expired and could not be refreshed. Reconnect this calendar.',
            ])->save();
        }

        return false;
    }
}
