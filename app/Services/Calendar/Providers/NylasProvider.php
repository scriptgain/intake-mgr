<?php

namespace App\Services\Calendar\Providers;

use App\Models\CalendarConnection;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Calendar\CalendarSettings;
use App\Services\Calendar\Contracts\CalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nylas v3: one integration that reaches Google, Microsoft, iCloud and IMAP
 * behind a single API. Nylas holds the provider tokens; we hold a GRANT ID.
 *
 * Hosted auth at {apiBase}/v3/connect/auth sends the staff member to Nylas, which
 * runs the provider's own OAuth and returns a code. exchangeCode posts it to
 * {apiBase}/v3/connect/token and gets back a grant_id (stored on the connection).
 * Every subsequent server call is authenticated with the app's API KEY as a
 * bearer token and scoped to /v3/grants/{grant}. There is no per-user token to
 * refresh: Nylas refreshes the underlying provider token itself.
 *
 * Nylas timespans are UNIX SECONDS, not RFC3339. NEVER THROWS; errors redacted.
 */
class NylasProvider implements CalendarProvider
{
    use BuildsEventData;

    public function key(): string
    {
        return 'nylas';
    }

    public function label(): string
    {
        return 'Nylas';
    }

    public function isEnabled(): bool
    {
        return CalendarSettings::enabled('nylas');
    }

    public function authorizeUrl(User $user, string $state): string
    {
        $query = http_build_query([
            'client_id' => CalendarSettings::nylasClientId(),
            'redirect_uri' => CalendarSettings::redirectUri('nylas'),
            'response_type' => 'code',
            // Nylas unifies providers, so the account chooser is left to the
            // hosted flow; offline access keeps the grant usable long-term.
            'access_type' => 'offline',
            'login_hint' => $user->email,
            'state' => $state,
        ]);

        return CalendarSettings::nylasApiBase().'/v3/connect/auth?'.$query;
    }

    public function exchangeCode(string $code): array
    {
        try {
            $response = Http::acceptJson()->timeout(20)->post(CalendarSettings::nylasApiBase().'/v3/connect/token', [
                'client_id' => CalendarSettings::nylasClientId(),
                // In v3 the API key doubles as the client secret for token exchange.
                'client_secret' => CalendarSettings::nylasApiKey(),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => CalendarSettings::redirectUri('nylas'),
            ]);

            if (! $response->successful()) {
                return $this->tokenFailure($response->json('error_description') ?? $response->json('error') ?? 'Nylas token exchange failed.');
            }

            $data = $response->json();

            // The grant id is the durable handle; the controller reads it from
            // the 'grant_id' key below and stores it on nylas_grant_id.
            $grant = (string) ($data['grant_id'] ?? '');

            if ($grant === '') {
                return $this->tokenFailure('Nylas did not return a grant for that account.');
            }

            return [
                'ok' => true,
                'access_token' => $grant,
                'refresh_token' => null,
                'expires_in' => null,
                'account_email' => $data['email'] ?? null,
                'scope' => isset($data['scope']) ? (is_array($data['scope']) ? implode(' ', $data['scope']) : (string) $data['scope']) : null,
                'error' => null,
                // Explicit, so the controller can store it without re-parsing.
                'grant_id' => $grant,
            ];
        } catch (\Throwable $e) {
            Log::warning('Nylas token exchange threw', ['exception' => class_basename($e)]);

            return $this->tokenFailure('Could not reach Nylas to complete the connection.');
        }
    }

    /** Nylas manages provider token refresh behind the grant; nothing to do here. */
    public function refresh(CalendarConnection $c): bool
    {
        return filled($c->nylas_grant_id);
    }

    public function createEvent(CalendarConnection $c, WorkOrder $wo): array
    {
        try {
            $response = $this->client()->post($this->eventsUrl($c), $this->eventBody($wo));

            return $this->eventResult($response, null);
        } catch (\Throwable $e) {
            return ['ok' => false, 'remote_event_id' => null, 'etag' => null, 'error' => $this->redactError('Could not reach Nylas.')];
        }
    }

    public function updateEvent(CalendarConnection $c, WorkOrder $wo, string $remoteEventId): array
    {
        try {
            $response = $this->client()->put($this->eventUrl($c, $remoteEventId), $this->eventBody($wo));

            return $this->eventResult($response, $remoteEventId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'remote_event_id' => null, 'etag' => null, 'error' => $this->redactError('Could not reach Nylas.')];
        }
    }

    public function deleteEvent(CalendarConnection $c, string $remoteEventId): array
    {
        try {
            $response = $this->client()->delete($this->eventUrl($c, $remoteEventId));

            if ($response->successful() || $response->status() === 404) {
                return ['ok' => true, 'error' => null];
            }

            return ['ok' => false, 'error' => $this->redactError($response->json('error.message') ?? 'Nylas could not delete the event.')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $this->redactError('Could not reach Nylas.')];
        }
    }

    public function listBusy(CalendarConnection $c, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        if (! filled($c->nylas_grant_id)) {
            return ['ok' => false, 'blocks' => [], 'error' => 'This connection has no Nylas grant.'];
        }

        $email = $c->account_email;

        if (! $email) {
            return ['ok' => false, 'blocks' => [], 'error' => 'This connection has no mailbox address to query.'];
        }

        try {
            $response = $this->client()->post(
                CalendarSettings::nylasApiBase().'/v3/grants/'.rawurlencode($c->nylas_grant_id).'/calendars/free-busy',
                [
                    'start_time' => CarbonImmutable::parse($from)->getTimestamp(),
                    'end_time' => CarbonImmutable::parse($to)->getTimestamp(),
                    'emails' => [$email],
                ]
            );

            if (! $response->successful()) {
                return ['ok' => false, 'blocks' => [], 'error' => $this->redactError($response->json('error.message') ?? 'Nylas free/busy lookup failed.')];
            }

            $slots = data_get($response->json(), 'data.0.time_slots', []);
            $blocks = [];
            foreach (is_array($slots) ? $slots : [] as $slot) {
                $start = $slot['start_time'] ?? null;
                $end = $slot['end_time'] ?? null;
                $status = (string) ($slot['status'] ?? 'busy');

                if ($start !== null && $end !== null && $status !== 'free') {
                    $blocks[] = [
                        'starts_at' => CarbonImmutable::createFromTimestampUTC((int) $start)->format('Y-m-d\TH:i:s\Z'),
                        'ends_at' => CarbonImmutable::createFromTimestampUTC((int) $end)->format('Y-m-d\TH:i:s\Z'),
                        'remote_event_id' => null,
                    ];
                }
            }

            return ['ok' => true, 'blocks' => $blocks, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'blocks' => [], 'error' => $this->redactError('Could not reach Nylas.')];
        }
    }

    public function testConnection(): array
    {
        if (! CalendarSettings::isConfigured('nylas')) {
            return ['ok' => false, 'message' => 'Set a Nylas API key and client ID first.'];
        }

        // List the application's grants with the API key: a 200 proves the key
        // is valid and the region is right, before any staff member connects.
        try {
            $response = $this->client()->get(CalendarSettings::nylasApiBase().'/v3/grants', ['limit' => 1]);

            if ($response->successful()) {
                return ['ok' => true, 'message' => 'Nylas API key accepted ('.strtoupper(CalendarSettings::nylasApiRegion()).' region). Staff can connect any calendar.'];
            }

            if ($response->status() === 401 || $response->status() === 403) {
                return ['ok' => false, 'message' => 'Nylas rejected that API key. Check the key and the data region.'];
            }

            return ['ok' => false, 'message' => 'Nylas returned HTTP '.$response->status().'. Try again shortly.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->redactError('Could not reach Nylas.')];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function eventBody(WorkOrder $wo): array
    {
        return [
            'title' => $this->eventSummary($wo),
            'description' => $this->eventDescription($wo),
            'location' => $this->eventLocation($wo),
            'when' => [
                'start_time' => $this->eventStart($wo)->getTimestamp(),
                'end_time' => $this->eventEnd($wo)->getTimestamp(),
            ],
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
            'remote_event_id' => data_get($data, 'data.id', $fallbackId),
            'etag' => null,
            'error' => null,
        ];
    }

    /** Collection URL with the write calendar selected. */
    private function eventsUrl(CalendarConnection $c): string
    {
        $calendar = $c->remote_calendar_id ?: 'primary';

        return CalendarSettings::nylasApiBase().'/v3/grants/'.rawurlencode((string) $c->nylas_grant_id).'/events?calendar_id='.rawurlencode($calendar);
    }

    /** A single-event URL keeping the calendar_id query Nylas requires on writes. */
    private function eventUrl(CalendarConnection $c, string $eventId): string
    {
        $calendar = $c->remote_calendar_id ?: 'primary';

        return CalendarSettings::nylasApiBase().'/v3/grants/'.rawurlencode((string) $c->nylas_grant_id).'/events/'.rawurlencode($eventId).'?calendar_id='.rawurlencode($calendar);
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        // Every server-side Nylas v3 call authenticates with the app API key.
        return Http::withToken((string) CalendarSettings::nylasApiKey())->acceptJson()->timeout(20);
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
            'grant_id' => null,
        ];
    }
}
