<?php

namespace App\Services\Calendar\Providers;

use App\Models\WorkOrder;
use App\Services\Payments\OrderPayments;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Shared derivation of a calendar event from a work order. Every provider maps
 * these same fields into its own wire format (Google/Graph JSON, VEVENT text,
 * Nylas JSON), so the mapping from the domain object lives here once.
 *
 * NEVER THROWS and REDACTS. Remote calls are wrapped by each provider; the
 * error() helper normalizes any surfaced message through OrderPayments::redact
 * so a credential can never reach a log line or a screen.
 */
trait BuildsEventData
{
    /** Default appointment length, in minutes, when a work order carries no duration. */
    private function defaultDurationMinutes(): int
    {
        return 60;
    }

    /** "WO-1001 Fix the pool pump" — number already carries the WO- prefix. */
    protected function eventSummary(WorkOrder $wo): string
    {
        $title = trim((string) $wo->title);

        return trim($wo->number.' '.$title);
    }

    /** The event start as an immutable UTC instant. */
    protected function eventStart(WorkOrder $wo): CarbonImmutable
    {
        $at = $wo->scheduled_at ? CarbonImmutable::parse($wo->scheduled_at) : CarbonImmutable::now();

        return $at->utc();
    }

    /** The event end = start + duration (default 60 minutes), in UTC. */
    protected function eventEnd(WorkOrder $wo): CarbonImmutable
    {
        $minutes = (int) ($wo->duration_minutes ?: $this->defaultDurationMinutes());

        if ($minutes < 1) {
            $minutes = $this->defaultDurationMinutes();
        }

        return $this->eventStart($wo)->addMinutes($minutes);
    }

    /** RFC3339 / ISO-8601 UTC, e.g. 2026-07-22T14:00:00Z. */
    protected function rfc3339(CarbonInterface $at): string
    {
        return $at->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** UTC in the iCalendar basic format used inside a VEVENT, e.g. 20260722T140000Z. */
    protected function icalStamp(CarbonInterface $at): string
    {
        return $at->utc()->format('Ymd\THis\Z');
    }

    /** Customer name/contact line plus the work order notes. */
    protected function eventDescription(WorkOrder $wo): string
    {
        $lines = [];

        if ($customer = $wo->customer) {
            $lines[] = 'Customer: '.$customer->name;
            if ($customer->phone) {
                $lines[] = 'Phone: '.$customer->phone;
            }
            if ($customer->email) {
                $lines[] = 'Email: '.$customer->email;
            }
        }

        if (filled($wo->notes)) {
            if ($lines) {
                $lines[] = '';
            }
            $lines[] = (string) $wo->notes;
        }

        return implode("\n", $lines);
    }

    /** The work order address flattened to a single line. */
    protected function eventLocation(WorkOrder $wo): string
    {
        $address = $wo->address;

        if (is_string($address)) {
            return trim($address);
        }

        if (! is_array($address)) {
            return '';
        }

        $parts = [];
        foreach ($address as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $parts[] = trim((string) $value);
            }
        }

        return implode(', ', $parts);
    }

    /** Normalize any surfaced error string, redacting anything credential-shaped. */
    protected function redactError(?string $message): string
    {
        return OrderPayments::redact($message ?: 'The calendar provider could not be reached.');
    }
}
