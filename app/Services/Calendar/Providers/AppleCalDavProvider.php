<?php

namespace App\Services\Calendar\Providers;

use App\Models\CalendarConnection;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Calendar\CalendarSettings;
use App\Services\Calendar\Contracts\CalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Apple iCloud Calendar via CalDAV. NOT OAuth.
 *
 * Each staff member connects with their Apple ID and an APP-SPECIFIC PASSWORD
 * (appleid.apple.com -> Sign-In and Security -> App-Specific Passwords), entered
 * on the My Calendar page. There are no app-level credentials, so the only
 * app-level setting is the on/off switch.
 *
 * DISCOVERY. iCloud does not tell you your calendar URL: you walk CalDAV to find
 * it. PROPFIND current-user-principal on caldav.icloud.com -> PROPFIND
 * calendar-home-set on the principal -> PROPFIND Depth 1 on the home to pick a
 * calendar collection that holds VEVENTs. iCloud also redirects you off
 * caldav.icloud.com onto a per-account partition host (p##-caldav.icloud.com), so
 * every discovered href is resolved against the response's effective host.
 *
 * WRITES are a PUT of a hand-built VEVENT to a .ics resource inside the chosen
 * collection; DELETE removes it; free/busy is a calendar-query REPORT parsed for
 * VEVENT DTSTART/DTEND. NEVER THROWS; errors are redacted.
 */
class AppleCalDavProvider implements CalendarProvider
{
    use BuildsEventData;

    private const DISCOVERY_ROOT = 'https://caldav.icloud.com';

    private const DAV_NS = 'DAV:';

    private const CALDAV_NS = 'urn:ietf:params:xml:data:caldav';

    public function key(): string
    {
        return 'apple';
    }

    public function label(): string
    {
        return 'Apple iCloud';
    }

    public function isEnabled(): bool
    {
        return CalendarSettings::enabled('apple');
    }

    /** Apple is not OAuth: it connects via a credential form, not a redirect. */
    public function authorizeUrl(User $user, string $state): string
    {
        return '';
    }

    /** Not applicable: Apple has no authorization code to exchange. */
    public function exchangeCode(string $code): array
    {
        return [
            'ok' => false,
            'access_token' => null,
            'refresh_token' => null,
            'expires_in' => null,
            'account_email' => null,
            'scope' => null,
            'error' => 'Apple Calendar connects with an app-specific password, not an authorization code.',
        ];
    }

    /**
     * CalDAV credentials do not expire, so there is no token to refresh: the
     * connection is usable as long as it still holds a password and a URL.
     */
    public function refresh(CalendarConnection $c): bool
    {
        return filled($c->caldav_password) && filled($c->caldav_url);
    }

    /**
     * Verify an Apple ID + app-specific password and discover the calendar to
     * write to. Called by the coordinator's connect controller, which then stores
     * caldav_url / caldav_username / caldav_password (encrypted) on the connection.
     *
     * @return array{ok:bool, account_email:?string, caldav_url:?string, username:?string, error:?string}
     */
    public function verify(string $email, string $appPassword): array
    {
        $email = trim($email);

        if ($email === '' || $appPassword === '') {
            return $this->verifyFailure('Enter your Apple ID and an app-specific password.');
        }

        try {
            // 1. The principal for these credentials. A 401 here is a bad
            //    Apple ID / password, surfaced plainly.
            $principalRes = $this->propfind($email, $appPassword, self::DISCOVERY_ROOT.'/', '0', $this->principalBody());

            if ($principalRes->status() === 401) {
                return $this->verifyFailure('Apple rejected that Apple ID or app-specific password.');
            }

            if (! $this->isMultiStatus($principalRes)) {
                return $this->verifyFailure('Could not reach iCloud CalDAV. Try again shortly.');
            }

            $principalHref = $this->firstHref($principalRes->body(), '//d:current-user-principal/d:href');
            if (! $principalHref) {
                return $this->verifyFailure('iCloud did not return a calendar principal for that account.');
            }
            $principalUrl = $this->resolveHref($principalRes, $principalHref);

            // 2. The calendar-home-set on the principal.
            $homeRes = $this->propfind($email, $appPassword, $principalUrl, '0', $this->homeBody());
            $homeHref = $this->firstHref($homeRes->body(), '//c:calendar-home-set/d:href');
            if (! $homeHref) {
                return $this->verifyFailure('iCloud did not return a calendar home for that account.');
            }
            $homeUrl = $this->resolveHref($homeRes, $homeHref);

            // 3. A calendar collection under the home that accepts VEVENTs.
            $collectionsRes = $this->propfind($email, $appPassword, $homeUrl, '1', $this->collectionsBody());
            $calendarUrl = $this->firstVeventCalendar($collectionsRes, $homeUrl);
            if (! $calendarUrl) {
                return $this->verifyFailure('No writable iCloud calendar was found for that account.');
            }

            return [
                'ok' => true,
                'account_email' => $email,
                'caldav_url' => $calendarUrl,
                'username' => $email,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return $this->verifyFailure($this->redactError('Could not reach iCloud CalDAV.'));
        }
    }

    public function createEvent(CalendarConnection $c, WorkOrder $wo): array
    {
        return $this->putEvent($c, $wo);
    }

    public function updateEvent(CalendarConnection $c, WorkOrder $wo, string $remoteEventId): array
    {
        // A CalDAV update is the same idempotent PUT to the same resource URL.
        return $this->putEvent($c, $wo, $remoteEventId);
    }

    public function deleteEvent(CalendarConnection $c, string $remoteEventId): array
    {
        if (! filled($c->caldav_url)) {
            return ['ok' => false, 'error' => 'This connection has no CalDAV calendar URL.'];
        }

        try {
            $url = $this->resourceUrl($c, $remoteEventId);
            $response = $this->authed($c)->delete($url);

            if ($response->successful() || $response->status() === 404) {
                return ['ok' => true, 'error' => null];
            }

            return ['ok' => false, 'error' => $this->redactError('iCloud could not delete the event (HTTP '.$response->status().').')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $this->redactError('Could not reach iCloud CalDAV.')];
        }
    }

    public function listBusy(CalendarConnection $c, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        if (! filled($c->caldav_url)) {
            return ['ok' => false, 'blocks' => [], 'error' => 'This connection has no CalDAV calendar URL.'];
        }

        try {
            $body = $this->timeRangeReportBody(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
            $response = $this->authed($c)
                ->withHeaders(['Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8'])
                ->withBody($body, 'application/xml')
                ->send('REPORT', $c->caldav_url);

            if (! $this->isMultiStatus($response)) {
                return ['ok' => false, 'blocks' => [], 'error' => $this->redactError('iCloud free/busy lookup failed (HTTP '.$response->status().').')];
            }

            return ['ok' => true, 'blocks' => $this->blocksFromReport($response->body()), 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'blocks' => [], 'error' => $this->redactError('Could not reach iCloud CalDAV.')];
        }
    }

    public function testConnection(): array
    {
        if (! CalendarSettings::switchIsOn('apple')) {
            return ['ok' => false, 'message' => 'Turn Apple Calendar on first.'];
        }

        // Apple has no app-level credentials to probe: confirm iCloud CalDAV is
        // reachable so staff know the connect form will work. An unauthenticated
        // request answers 401, which still proves reachability.
        try {
            $response = Http::timeout(15)->send('OPTIONS', self::DISCOVERY_ROOT.'/');

            if ($response->status() > 0 && ! $response->serverError()) {
                return ['ok' => true, 'message' => 'iCloud CalDAV is reachable. Staff connect with an Apple ID and an app-specific password on the My Calendar page.'];
            }

            return ['ok' => false, 'message' => 'iCloud CalDAV did not answer. Try again shortly.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->redactError('Could not reach iCloud CalDAV.')];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Writing events
    |--------------------------------------------------------------------------
    */

    private function putEvent(CalendarConnection $c, WorkOrder $wo, ?string $remoteEventId = null): array
    {
        if (! filled($c->caldav_url)) {
            return ['ok' => false, 'remote_event_id' => null, 'etag' => null, 'error' => 'This connection has no CalDAV calendar URL.'];
        }

        try {
            // A stable UID keyed to the work order, so an update re-PUTs the same
            // resource rather than creating a duplicate.
            $uid = $remoteEventId ? $this->uidFromResource($remoteEventId) : 'wo-'.$wo->id.'-'.$c->id.'@intakemgr';
            $resource = $remoteEventId ?: ($uid.'.ics');
            $url = $this->resourceUrl($c, $resource);

            $response = $this->authed($c)
                ->withHeaders(['Content-Type' => 'text/calendar; charset=utf-8'])
                ->withBody($this->buildVevent($wo, $uid), 'text/calendar')
                ->put($url);

            if (! $response->successful()) {
                return ['ok' => false, 'remote_event_id' => null, 'etag' => null, 'error' => $this->redactError('iCloud rejected the event (HTTP '.$response->status().').')];
            }

            return [
                'ok' => true,
                // The resource path is the stable handle for later update/delete.
                'remote_event_id' => $resource,
                'etag' => $response->header('ETag') ?: null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'remote_event_id' => null, 'etag' => null, 'error' => $this->redactError('Could not reach iCloud CalDAV.')];
        }
    }

    /** A folded, escaped VEVENT wrapped in a VCALENDAR, CRLF-terminated. */
    private function buildVevent(WorkOrder $wo, string $uid): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//IntakeMGR//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:'.$this->escapeText($uid),
            'DTSTAMP:'.$this->icalStamp(CarbonImmutable::now()),
            'DTSTART:'.$this->icalStamp($this->eventStart($wo)),
            'DTEND:'.$this->icalStamp($this->eventEnd($wo)),
            'SUMMARY:'.$this->escapeText($this->eventSummary($wo)),
            'DESCRIPTION:'.$this->escapeText($this->eventDescription($wo)),
            'LOCATION:'.$this->escapeText($this->eventLocation($wo)),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    /** Escape TEXT values per RFC 5545 (backslash, comma, semicolon, newline). */
    private function escapeText(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
        $value = str_replace([',', ';'], ['\\,', '\\;'], $value);

        return $value;
    }

    /** Absolute URL of an .ics resource inside the connection's calendar collection. */
    private function resourceUrl(CalendarConnection $c, string $resource): string
    {
        // Already absolute (an update/delete handing back what create stored).
        if (preg_match('#^https?://#i', $resource)) {
            return $resource;
        }

        return rtrim((string) $c->caldav_url, '/').'/'.ltrim($resource, '/');
    }

    private function uidFromResource(string $resource): string
    {
        $base = basename(parse_url($resource, PHP_URL_PATH) ?: $resource);

        return preg_replace('/\.ics$/i', '', $base) ?: $resource;
    }

    /*
    |--------------------------------------------------------------------------
    | Discovery + parsing
    |--------------------------------------------------------------------------
    */

    private function principalBody(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<d:propfind xmlns:d="DAV:"><d:prop><d:current-user-principal/></d:prop></d:propfind>';
    }

    private function homeBody(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:data:caldav">'
            .'<d:prop><c:calendar-home-set/></d:prop></d:propfind>';
    }

    private function collectionsBody(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:data:caldav">'
            .'<d:prop><d:resourcetype/><d:displayname/><c:supported-calendar-component-set/></d:prop></d:propfind>';
    }

    private function timeRangeReportBody(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:data:caldav">'
            .'<d:prop><c:calendar-data/></d:prop>'
            .'<c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT">'
            .'<c:time-range start="'.$this->icalStamp($from).'" end="'.$this->icalStamp($to).'"/>'
            .'</c:comp-filter></c:comp-filter></c:filter></c:calendar-query>';
    }

    private function propfind(string $user, string $pass, string $url, string $depth, string $body): \Illuminate\Http\Client\Response
    {
        return Http::withBasicAuth($user, $pass)
            ->withHeaders(['Depth' => $depth, 'Content-Type' => 'application/xml; charset=utf-8'])
            ->withBody($body, 'application/xml')
            ->timeout(20)
            ->send('PROPFIND', $url);
    }

    private function authed(CalendarConnection $c): PendingRequest
    {
        return Http::withBasicAuth((string) $c->caldav_username, (string) $c->caldav_password)->timeout(20);
    }

    private function isMultiStatus(\Illuminate\Http\Client\Response $response): bool
    {
        return $response->status() === 207 || $response->status() === 200;
    }

    /** First href matched by an xpath in a DAV multistatus body. */
    private function firstHref(string $xml, string $xpath): ?string
    {
        $doc = $this->parseXml($xml);
        if (! $doc) {
            return null;
        }

        $nodes = $doc->xpath($xpath);
        if (! $nodes) {
            return null;
        }

        $href = trim((string) $nodes[0]);

        return $href !== '' ? $href : null;
    }

    /** Pick the first calendar collection that supports the VEVENT component. */
    private function firstVeventCalendar(\Illuminate\Http\Client\Response $response, string $baseHrefSource): ?string
    {
        $doc = $this->parseXml($response->body());
        if (! $doc) {
            return null;
        }

        foreach ($doc->xpath('//d:response') ?: [] as $node) {
            $node->registerXPathNamespace('d', self::DAV_NS);
            $node->registerXPathNamespace('c', self::CALDAV_NS);

            $isCalendar = $node->xpath('.//d:resourcetype/c:calendar');
            if (! $isCalendar) {
                continue;
            }

            // Must accept VEVENTs. iCloud has task-only and other collections too.
            $comps = $node->xpath('.//c:supported-calendar-component-set/c:comp');
            $supportsEvent = false;
            foreach ($comps ?: [] as $comp) {
                if (strtoupper((string) $comp['name']) === 'VEVENT') {
                    $supportsEvent = true;
                    break;
                }
            }
            // A collection with no explicit component set is treated as usable.
            if ($comps && ! $supportsEvent) {
                continue;
            }

            $hrefNodes = $node->xpath('./d:href');
            $href = $hrefNodes ? trim((string) $hrefNodes[0]) : '';
            if ($href !== '') {
                return $this->resolveHref($response, $href);
            }
        }

        return null;
    }

    /** Blocks from a calendar-query REPORT: DTSTART/DTEND out of each VEVENT. */
    private function blocksFromReport(string $xml): array
    {
        $doc = $this->parseXml($xml);
        if (! $doc) {
            return [];
        }

        $blocks = [];
        foreach ($doc->xpath('//c:calendar-data') ?: [] as $data) {
            $ics = (string) $data;
            $start = $this->icsProperty($ics, 'DTSTART');
            $end = $this->icsProperty($ics, 'DTEND');

            if ($start && $end) {
                $blocks[] = [
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'remote_event_id' => $this->icsProperty($ics, 'UID'),
                ];
            }
        }

        return $blocks;
    }

    /** Read a single ICS property value, tolerating TZID and other params. */
    private function icsProperty(string $ics, string $name): ?string
    {
        if (! preg_match('/^'.preg_quote($name, '/').'(?:;[^:\r\n]*)?:(.+)$/mi', $ics, $m)) {
            return null;
        }

        $raw = trim($m[1]);

        // Property values are the last thing we do not want to throw over, so a
        // parse failure just drops the block rather than bubbling up.
        try {
            if (in_array($name, ['DTSTART', 'DTEND'], true)) {
                return CarbonImmutable::parse($raw)->utc()->format('Y-m-d\TH:i:s\Z');
            }
        } catch (\Throwable $e) {
            return null;
        }

        return $raw;
    }

    private function parseXml(string $xml): ?\SimpleXMLElement
    {
        if (trim($xml) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $doc) {
            return null;
        }

        $doc->registerXPathNamespace('d', self::DAV_NS);
        $doc->registerXPathNamespace('c', self::CALDAV_NS);

        return $doc;
    }

    /** Resolve an href (possibly relative) against the response's effective host. */
    private function resolveHref(\Illuminate\Http\Client\Response $response, string $href): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $effective = (string) $response->effectiveUri();
        $parts = parse_url($effective);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'caldav.icloud.com';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port.'/'.ltrim($href, '/');
    }

    private function verifyFailure(string $message): array
    {
        return ['ok' => false, 'account_email' => null, 'caldav_url' => null, 'username' => null, 'error' => $message];
    }
}
