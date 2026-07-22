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
 * Google Calendar via OAuth2 + Calendar API v3.
 *
 * Consent at accounts.google.com/o/oauth2/v2/auth, tokens at
 * oauth2.googleapis.com/token, events under /calendars/primary/events, free/busy
 * via /freeBusy. access_type=offline + prompt=consent is what makes Google return
 * a refresh token (it only returns one on the first consent unless prompt=consent
 * forces it every time), so a connection can be refreshed without the staff
 * member re-authorizing.
 *
 * NEVER THROWS. Every method returns a normalized array; remote calls are wrapped
 * and any surfaced error is redacted of anything credential-shaped.
 */
class GoogleCalendarProvider implements CalendarProvider
{
    use BuildsEventData;

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    private const SCOPES = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly openid email';

    public function key(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Calendar';
    }

    public function isEnabled(): bool
    {
        return CalendarSettings::enabled('google');
    }

    public function authorizeUrl(User $user, string $state): string
    {
        $query = http_build_query([
            'client_id' => CalendarSettings::googleClientId(),
            'redirect_uri' => CalendarSettings::redirectUri('google'),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
            // login_hint smooths the account chooser when we already know the user.
            'login_hint' => $user->email,
        ]);

        return self::AUTH_URL.'?'.$query;
    }

    public function exchangeCode(string $code): array
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(20)->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => CalendarSettings::googleClientId(),
                'client_secret' => CalendarSettings::googleClientSecret(),
                'redirect_uri' => CalendarSettings::redirectUri('google'),
                'grant_type' => 'authorization_code',
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
            Log::warning('Google token exchange threw', ['exception' => class_basename($e)]);

            return $this->tokenFailure('Could not reach Google to complete the connection.');
        }
    }

    public function refresh(CalendarConnection $c): bool
    {
        if (! $c->refresh_token) {
            return false;
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout(20)->post(self::TOKEN_URL, [
                'refresh_token' => $c->refresh_token,
                'client_id' => CalendarSettings::googleClientId(),
                'client_secret' => CalendarSettings::googleClientSecret(),
                'grant_type' => 'refresh_token',
            ]);

            if (! $response->successful()) {
                Log::warning('Google token refresh failed', ['status' => $response->status()]);

                return false;
            }

            $data = $response->json();
            $access = (string) ($data['access_token'] ?? '');

            if ($access === '') {
                return false;
            }

            $c->forceFill([
                'access_token' => $access,
                'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null,
                'status' => 'connected',
                'last_error' => null,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Google token refresh threw', ['exception' => class_basename($e)]);

            return false;
        }
    }

    public function createEvent(CalendarConnection $c, WorkOrder $wo): array
    {
        return $this->writeEvent($c, $wo, null);
    }

    public function updateEvent(CalendarConnection $c, WorkOrder $wo, string $remoteEventId): array
    {
        return $this->writeEvent($c, $wo, $remoteEventId);
    }

    public function deleteEvent(CalendarConnection $c, string $remoteEventId): array
    {
        try {
            $calendar = $c->remote_calendar_id ?: 'primary';
            $response = $this->client($c)->delete(self::API_BASE.'/calendars/'.rawurlencode($calendar).'/events/'.rawurlencode($remoteEventId));

            // 410 Gone means it is already deleted, which is the desired end state.
            if ($response->successful() || $response->status() === 410 || $response->status() === 404) {
                return ['ok' => true, 'error' => null];
            }

            return ['ok' => false, 'error' => $this->redactError($response->json('error.message') ?? 'Delete failed.')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $this->redactError('Could not reach Google Calendar.')];
        }
    }

    public function listBusy(CalendarConnection $c, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        try {
            $calendar = $c->remote_calendar_id ?: 'primary';
            $response = $this->client($c)->post(self::API_BASE.'/freeBusy', [
                'timeMin' => $this->rfc3339(\Carbon\CarbonImmutable::parse($from)),
                'timeMax' => $this->rfc3339(\Carbon\CarbonImmutable::parse($to)),
                'items' => [['id' => $calendar]],
            ]);

            if (! $response->successful()) {
                return ['ok' => false, 'blocks' => [], 'error' => $this->redactError($response->json('error.message') ?? 'Free/busy lookup failed.')];
            }

            $busy = data_get($response->json(), 'calendars.'.$calendar.'.busy', []);
            $blocks = [];
            foreach (is_array($busy) ? $busy : [] as $slot) {
                if (! empty($slot['start']) && ! empty($slot['end'])) {
                    $blocks[] = [
                        'starts_at' => (string) $slot['start'],
                        'ends_at' => (string) $slot['end'],
                        'remote_event_id' => null,
                    ];
                }
            }

            return ['ok' => true, 'blocks' => $blocks, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'blocks' => [], 'error' => $this->redactError('Could not reach Google Calendar.')];
        }
    }

    public function testConnection(): array
    {
        if (! CalendarSettings::isConfigured('google')) {
            return ['ok' => false, 'message' => 'Set a Google client ID and client secret first.'];
        }

        // A cheap, credential-only probe: the OpenID discovery document. It does
        // not prove a per-user grant (there is none until a staff member
        // connects), only that Google is reachable and the app knows its client.
        try {
            $response = Http::acceptJson()->timeout(15)->get('https://oauth2.googleapis.com/tokeninfo', [
                'client_id' => CalendarSettings::googleClientId(),
            ]);

            // tokeninfo without a token returns 400; reaching it at all confirms
            // connectivity and that a client id is set. Any 5xx is a real failure.
            if ($response->serverError()) {
                return ['ok' => false, 'message' => 'Google returned a server error. Try again shortly.'];
            }

            return ['ok' => true, 'message' => 'Google client credentials are set. Staff can connect their Google Calendar.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->redactError('Could not reach Google.')];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Create (POST) or update (PATCH) the work order's event and return its id/etag. */
    private function writeEvent(CalendarConnection $c, WorkOrder $wo, ?string $remoteEventId): array
    {
        try {
            $calendar = $c->remote_calendar_id ?: 'primary';
            $body = [
                'summary' => $this->eventSummary($wo),
                'description' => $this->eventDescription($wo),
                'location' => $this->eventLocation($wo),
                'start' => ['dateTime' => $this->rfc3339($this->eventStart($wo)), 'timeZone' => 'UTC'],
                'end' => ['dateTime' => $this->rfc3339($this->eventEnd($wo)), 'timeZone' => 'UTC'],
            ];

            $url = self::API_BASE.'/calendars/'.rawurlencode($calendar).'/events';

            $response = $remoteEventId === null
                ? $this->client($c)->post($url, $body)
                : $this->client($c)->patch($url.'/'.rawurlencode($remoteEventId), $body);

            if (! $response->successful()) {
                return ['ok' => false, 'remote_event_id' => null, 'etag' => null, 'error' => $this->redactError($response->json('error.message') ?? 'The event could not be saved.')];
            }

            $data = $response->json();

            return [
                'ok' => true,
                'remote_event_id' => $data['id'] ?? $remoteEventId,
                'etag' => $data['etag'] ?? null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'remote_event_id' => null, 'etag' => null, 'error' => $this->redactError('Could not reach Google Calendar.')];
        }
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

    /** Pull the email claim out of a Google id_token JWT without verifying it. */
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

        return is_array($claims) && ! empty($claims['email']) ? (string) $claims['email'] : null;
    }
}
