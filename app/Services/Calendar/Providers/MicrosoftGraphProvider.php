<?php

namespace App\Services\Calendar\Providers;

use App\Models\CalendarConnection;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Calendar\CalendarSettings;
use App\Services\Calendar\Contracts\CalendarProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft Outlook / Microsoft 365 via OAuth2 + Microsoft Graph.
 *
 * Consent and token under login.microsoftonline.com/{tenant}/oauth2/v2.0/*
 * (tenant 'common' covers both work/school and personal accounts), events under
 * Graph /me/events, free/busy via /me/calendar/getSchedule. offline_access is
 * what makes Graph return a refresh token; openid + email give the id_token we
 * read the signed-in address from.
 *
 * GRAPH DATETIMES CARRY NO ZONE SUFFIX. Graph wants a naive local datetime plus a
 * separate timeZone field, so start/end are formatted without the trailing Z and
 * tagged timeZone: UTC — a Z here is rejected.
 *
 * NEVER THROWS; surfaced errors are redacted of anything credential-shaped.
 */
class MicrosoftGraphProvider implements CalendarProvider
{
    use BuildsEventData;

    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    private const SCOPES = 'offline_access openid email Calendars.ReadWrite';

    public function key(): string
    {
        return 'microsoft';
    }

    public function label(): string
    {
        return 'Microsoft Outlook';
    }

    public function isEnabled(): bool
    {
        return CalendarSettings::enabled('microsoft');
    }

    public function authorizeUrl(User $user, string $state): string
    {
        $query = http_build_query([
            'client_id' => CalendarSettings::microsoftClientId(),
            'response_type' => 'code',
            'redirect_uri' => CalendarSettings::redirectUri('microsoft'),
            'response_mode' => 'query',
            'scope' => self::SCOPES,
            'state' => $state,
            'login_hint' => $user->email,
        ]);

        return $this->authority().'/oauth2/v2.0/authorize?'.$query;
    }

    public function exchangeCode(string $code): array
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(20)->post($this->authority().'/oauth2/v2.0/token', [
                'client_id' => CalendarSettings::microsoftClientId(),
                'client_secret' => CalendarSettings::microsoftClientSecret(),
                'redirect_uri' => CalendarSettings::redirectUri('microsoft'),
                'grant_type' => 'authorization_code',
                'scope' => self::SCOPES,
                'code' => $code,
            ]);

            if (! $response->successful()) {
                return $this->tokenFailure($response->json('error_description') ?? $response->json('error') ?? 'Token exchange failed.');
            }

            $data = $response->json();

            return [
                'ok' => true,
                'access_token' => (string) ($data['access_token'] ?? ''),
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
                'account_email' => $this->emailFromIdToken($data['id_token'] ?? null),
                'scope' => $data['scope'] ?? self::SCOPES,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Microsoft token exchange threw', ['exception' => class_basename($e)]);

            return $this->tokenFailure('Could not reach Microsoft to complete the connection.');
        }
    }

    public function refresh(CalendarConnection $c): bool
    {
        if (! $c->refresh_token) {
            return false;
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout(20)->post($this->authority().'/oauth2/v2.0/token', [
                'client_id' => CalendarSettings::microsoftClientId(),
                'client_secret' => CalendarSettings::microsoftClientSecret(),
                'grant_type' => 'refresh_token',
                'scope' => self::SCOPES,
                'refresh_token' => $c->refresh_token,
            ]);

            if (! $response->successful()) {
                Log::warning('Microsoft token refresh failed', ['status' => $response->status()]);

                return false;
            }

            $data = $response->json();
            $access = (string) ($data['access_token'] ?? '');

            if ($access === '') {
                return false;
            }

            $c->forceFill([
                'access_token' => $access,
                // Microsoft rotates refresh tokens: persist the new one when sent.
                'refresh_token' => $data['refresh_token'] ?? $c->refresh_token,
                'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null,
                'status' => 'connected',
                'last_error' => null,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Microsoft token refresh threw', ['exception' => class_basename($e)]);

            return false;
        }
    }

    public function createEvent(CalendarConnection $c, WorkOrder $wo): array
    {
        try {
            $response = $this->client($c)->post(self::GRAPH_BASE.'/me/events', $this->eventBody($wo));

            return $this->eventResult($response, null);
        } catch (\Throwable $e) {
            return ['ok' => false, 'remote_event_id' => null, 'etag' => null, 'error' => $this->redactError('Could not reach Microsoft Graph.')];
        }
    }

    public function updateEvent(CalendarConnection $c, WorkOrder $wo, string $remoteEventId): array
    {
        try {
            $response = $this->client($c)->patch(self::GRAPH_BASE.'/me/events/'.rawurlencode($remoteEventId), $this->eventBody($wo));

            return $this->eventResult($response, $remoteEventId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'remote_event_id' => null, 'etag' => null, 'error' => $this->redactError('Could not reach Microsoft Graph.')];
        }
    }

    public function deleteEvent(CalendarConnection $c, string $remoteEventId): array
    {
        try {
            $response = $this->client($c)->delete(self::GRAPH_BASE.'/me/events/'.rawurlencode($remoteEventId));

            if ($response->successful() || $response->status() === 404) {
                return ['ok' => true, 'error' => null];
            }

            return ['ok' => false, 'error' => $this->redactError($response->json('error.message') ?? 'Delete failed.')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $this->redactError('Could not reach Microsoft Graph.')];
        }
    }

    public function listBusy(CalendarConnection $c, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $schedule = $c->account_email;

        if (! $schedule) {
            return ['ok' => false, 'blocks' => [], 'error' => 'This connection has no mailbox address to query.'];
        }

        try {
            $response = $this->client($c)->post(self::GRAPH_BASE.'/me/calendar/getSchedule', [
                'schedules' => [$schedule],
                'startTime' => ['dateTime' => $this->graphDateTime(\Carbon\CarbonImmutable::parse($from)), 'timeZone' => 'UTC'],
                'endTime' => ['dateTime' => $this->graphDateTime(\Carbon\CarbonImmutable::parse($to)), 'timeZone' => 'UTC'],
                'availabilityViewInterval' => 30,
            ]);

            if (! $response->successful()) {
                return ['ok' => false, 'blocks' => [], 'error' => $this->redactError($response->json('error.message') ?? 'Free/busy lookup failed.')];
            }

            $items = data_get($response->json(), 'value.0.scheduleItems', []);
            $blocks = [];
            foreach (is_array($items) ? $items : [] as $item) {
                $start = data_get($item, 'start.dateTime');
                $end = data_get($item, 'end.dateTime');
                $status = (string) data_get($item, 'status', 'busy');

                // 'free' items are not blocking; everything else (busy, tentative,
                // oof, workingElsewhere) occupies the technician.
                if ($start && $end && $status !== 'free') {
                    $blocks[] = [
                        'starts_at' => (string) $start,
                        'ends_at' => (string) $end,
                        'remote_event_id' => null,
                    ];
                }
            }

            return ['ok' => true, 'blocks' => $blocks, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'blocks' => [], 'error' => $this->redactError('Could not reach Microsoft Graph.')];
        }
    }

    public function testConnection(): array
    {
        if (! CalendarSettings::isConfigured('microsoft')) {
            return ['ok' => false, 'message' => 'Set a Microsoft client ID and client secret first.'];
        }

        // Fetch the tenant's OpenID configuration: it 200s only when the tenant
        // is valid and reachable, proving the app-level config is sound before a
        // staff member tries to connect.
        try {
            $response = Http::acceptJson()->timeout(15)->get($this->authority().'/v2.0/.well-known/openid-configuration');

            if ($response->successful()) {
                return ['ok' => true, 'message' => 'Microsoft tenant "'.CalendarSettings::microsoftTenant().'" is reachable. Staff can connect Outlook.'];
            }

            return ['ok' => false, 'message' => 'Microsoft rejected tenant "'.CalendarSettings::microsoftTenant().'". Check the tenant value.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->redactError('Could not reach Microsoft.')];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function authority(): string
    {
        return 'https://login.microsoftonline.com/'.rawurlencode(CalendarSettings::microsoftTenant());
    }

    private function eventBody(WorkOrder $wo): array
    {
        return [
            'subject' => $this->eventSummary($wo),
            'body' => ['contentType' => 'text', 'content' => $this->eventDescription($wo)],
            'location' => ['displayName' => $this->eventLocation($wo)],
            'start' => ['dateTime' => $this->graphDateTime($this->eventStart($wo)), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $this->graphDateTime($this->eventEnd($wo)), 'timeZone' => 'UTC'],
        ];
    }

    private function eventResult(\Illuminate\Http\Client\Response $response, ?string $fallbackId): array
    {
        if (! $response->successful()) {
            return ['ok' => false, 'remote_event_id' => null, 'etag' => null, 'error' => $this->redactError($response->json('error.message') ?? 'The event could not be saved.')];
        }

        $data = $response->json();

        return [
            'ok' => true,
            'remote_event_id' => $data['id'] ?? $fallbackId,
            'etag' => $data['@odata.etag'] ?? null,
            'error' => null,
        ];
    }

    /** Naive local datetime for Graph, e.g. 2026-07-22T14:00:00 (no zone suffix). */
    private function graphDateTime(\Carbon\CarbonInterface $at): string
    {
        return $at->utc()->format('Y-m-d\TH:i:s');
    }

    private function client(CalendarConnection $c): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken((string) $c->access_token)->acceptJson()->timeout(20);
    }

    private function tokenFailure(string $message): array
    {
        return [
            'ok' => false,
            'access_token' => null,
            'refresh_token' => null,
            'expires_in' => null,
            'account_email' => null,
            'scope' => null,
            'error' => $this->redactError($message),
        ];
    }

    /** Pull the email/preferred_username claim out of an id_token without verifying it. */
    private function emailFromIdToken(?string $idToken): ?string
    {
        if (! $idToken || substr_count($idToken, '.') < 2) {
            return null;
        }

        $payload = explode('.', $idToken)[1] ?? '';
        $json = base64_decode(strtr($payload, '-_', '+/'), true);

        if ($json === false) {
            return null;
        }

        $claims = json_decode($json, true);

        if (! is_array($claims)) {
            return null;
        }

        foreach (['email', 'preferred_username', 'upn'] as $claim) {
            if (! empty($claims[$claim])) {
                return (string) $claims[$claim];
            }
        }

        return null;
    }
}
