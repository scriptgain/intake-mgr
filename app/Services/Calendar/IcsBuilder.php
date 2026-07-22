<?php

namespace App\Services\Calendar;

use App\Models\WorkOrder;
use Illuminate\Support\Carbon;

/**
 * Hand-rolled iCalendar (RFC 5545) output for work orders. No dependency: the
 * format is small and stable, and this is the single place text is escaped and
 * timestamps are emitted as UTC, for both the Add-to-Calendar download and the
 * per-staff subscription feed.
 */
class IcsBuilder
{
    /** A full VCALENDAR wrapping one or more work orders. */
    public function calendar(iterable $workOrders, string $name = 'Schedule'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//IntakeMGR//Service Desk//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($name),
        ];

        foreach ($workOrders as $wo) {
            $event = $this->eventLines($wo);
            if ($event !== []) {
                $lines = array_merge($lines, $event);
            }
        }

        $lines[] = 'END:VCALENDAR';

        return $this->fold($lines);
    }

    /** A single-event VCALENDAR (the Add-to-Calendar download for one work order). */
    public function forWorkOrder(WorkOrder $workOrder): string
    {
        return $this->calendar([$workOrder], $workOrder->number);
    }

    /** @return array<int,string> the VEVENT lines, or [] if the WO has no scheduled time. */
    private function eventLines(WorkOrder $workOrder): array
    {
        if (! $workOrder->scheduled_at) {
            return [];
        }

        $start = $workOrder->scheduled_at->copy()->utc();
        $minutes = (int) ($workOrder->duration_minutes ?: 60);
        $end = $start->copy()->addMinutes($minutes);

        $summary = trim($workOrder->number.' '.($workOrder->title ?: 'Service Visit'));
        $customer = $workOrder->customer?->name;

        $descParts = array_filter([
            $customer ? 'Customer: '.$customer : null,
            $workOrder->assignee ? 'Technician: '.$workOrder->assignee->name : null,
            'Status: '.$workOrder->status_label,
            $workOrder->notes,
        ]);

        $lines = [
            'BEGIN:VEVENT',
            'UID:workorder-'.$workOrder->id.'@'.$this->host(),
            'DTSTAMP:'.$this->utc(Carbon::now()),
            'DTSTART:'.$this->utc($start),
            'DTEND:'.$this->utc($end),
            'SUMMARY:'.$this->escape($summary),
        ];

        if ($descParts !== []) {
            $lines[] = 'DESCRIPTION:'.$this->escape(implode('\n', $descParts));
        }

        if ($location = $this->location($workOrder)) {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }

        $lines[] = 'STATUS:'.($workOrder->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function location(WorkOrder $workOrder): ?string
    {
        $a = $workOrder->address;
        if (! is_array($a)) {
            return null;
        }
        $parts = array_filter([
            $a['line1'] ?? null, $a['line2'] ?? null, $a['city'] ?? null,
            trim(($a['state'] ?? '').' '.($a['postcode'] ?? '')),
        ], fn ($p) => filled($p));

        return $parts ? implode(', ', $parts) : null;
    }

    private function utc(Carbon $dt): string
    {
        return $dt->copy()->utc()->format('Ymd\THis\Z');
    }

    /** Escape per RFC 5545: backslash, comma, semicolon, newlines. */
    private function escape(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(["\r\n", "\n", "\r"], '\n', $text);

        return str_replace([',', ';'], ['\,', '\;'], $text);
    }

    /** Fold lines to 75 octets and join with CRLF, per the spec. */
    private function fold(array $lines): string
    {
        $out = [];
        foreach ($lines as $line) {
            while (strlen($line) > 75) {
                $out[] = substr($line, 0, 75);
                $line = ' '.substr($line, 75);
            }
            $out[] = $line;
        }

        return implode("\r\n", $out)."\r\n";
    }

    private function host(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'intakemgr.local';
    }
}
