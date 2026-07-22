<?php

namespace App\Services\Calendar\Contracts;

use App\Models\CalendarConnection;
use App\Models\User;

/**
 * One external calendar provider. IntakeMGR ships Google (OAuth2 + Calendar v3),
 * Microsoft (OAuth2 + Graph), Apple (CalDAV + app-specific password) and Nylas
 * (unified v3 grants). The sync layer, the availability free/busy computation and
 * the settings screen are all provider-neutral; a provider is only the transport
 * that talks to the remote calendar plus the OAuth/credential dance to connect it.
 *
 * EVERY METHOD RETURNS NORMALIZED DATA AND NEVER THROWS. Remote calls are wrapped
 * so a failed sync surfaces as {ok:false, error} the caller can log and show,
 * rather than a white screen. Secrets are redacted out of any surfaced error.
 */
interface CalendarProvider
{
    /** Stable machine key stored on calendar_connections.provider, e.g. 'google'. */
    public function key(): string;

    /** Human label for admin/staff UI, e.g. 'Google Calendar'. */
    public function label(): string;

    /** Switched on by an admin AND its app-level credentials are present. */
    public function isEnabled(): bool;

    /**
     * The OAuth consent URL to send a staff member to when connecting. $state is
     * the opaque CSRF/round-trip token the callback validates. Apple returns ''
     * because it is not OAuth: it connects via a credential form, not a redirect.
     */
    public function authorizeUrl(User $user, string $state): string;

    /**
     * Exchange an OAuth authorization code for tokens.
     *
     * @return array{ok:bool, access_token?:string, refresh_token?:?string, expires_in?:?int, account_email?:?string, scope?:?string, error:?string}
     */
    public function exchangeCode(string $code): array;

    /**
     * Refresh an expired access token and PERSIST it on the connection. Returns
     * whether the connection now holds a usable access token.
     */
    public function refresh(CalendarConnection $c): bool;

    /**
     * Create the remote event that mirrors a work order.
     *
     * @return array{ok:bool, remote_event_id?:?string, etag?:?string, error:?string}
     */
    public function createEvent(CalendarConnection $c, \App\Models\WorkOrder $wo): array;

    /**
     * Update the remote event previously created for a work order.
     *
     * @return array{ok:bool, remote_event_id?:?string, etag?:?string, error:?string}
     */
    public function updateEvent(CalendarConnection $c, \App\Models\WorkOrder $wo, string $remoteEventId): array;

    /**
     * Delete a remote event.
     *
     * @return array{ok:bool, error:?string}
     */
    public function deleteEvent(CalendarConnection $c, string $remoteEventId): array;

    /**
     * List busy blocks between two instants, used to compute real free/busy for
     * a technician's availability.
     *
     * @return array{ok:bool, blocks:array<int, array{starts_at:string, ends_at:string, remote_event_id?:?string}>, error:?string}
     */
    public function listBusy(CalendarConnection $c, \DateTimeInterface $from, \DateTimeInterface $to): array;

    /**
     * A lightweight credential probe for the settings Test button. Confirms the
     * app-level credentials answer before a staff member is the one who finds out
     * they do not.
     *
     * @return array{ok:bool, message:?string}
     */
    public function testConnection(): array;
}
