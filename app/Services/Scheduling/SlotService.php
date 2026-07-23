<?php

namespace App\Services\Scheduling;

use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\BookingType;
use App\Models\CalendarBusyBlock;
use App\Models\CalendarConnection;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;

/**
 * Computes the open appointment slots for a booking type.
 *
 * A slot is offered only when the assignee is working then (weekly availability
 * rules, overridden by date exceptions), the whole slot plus its before/after
 * buffers is free of any connected-calendar busy time AND any existing work
 * order, and it is far enough in the future to honour the lead time. All maths is
 * done in the assignee's own timezone, then compared as UTC instants.
 */
class SlotService
{
    /** Minimum notice before the first bookable slot. */
    private const LEAD_MINUTES = 120;

    /**
     * Open slots grouped by local date.
     *
     * @return array<int, array{date:string, label:string, slots:array<int, array{start:string, label:string}>}>
     */
    public function availableSlots(BookingType $type, CarbonImmutable $from, CarbonImmutable $to, ?int $excludeWorkOrderId = null): array
    {
        $assignee = $type->assignee;
        if (! $assignee) {
            return [];
        }

        $tz = $assignee->effectiveTimezone();
        $now = CarbonImmutable::now();
        $earliest = $now->addMinutes(self::LEAD_MINUTES);

        // Window bounds as UTC, padded a day each side so busy times that straddle
        // midnight are still fetched.
        $rangeStart = $from->startOfDay()->subDay();
        $rangeEnd = $to->endOfDay()->addDay();

        $busy = $this->busyIntervals($assignee, $rangeStart, $rangeEnd, $excludeWorkOrderId);
        [$rules, $exceptions] = $this->schedule($assignee);

        $length = (int) $type->duration_minutes ?: 60;
        $bufBefore = (int) $type->buffer_before_minutes;
        $bufAfter = (int) $type->buffer_after_minutes;

        $out = [];
        // Iterate whole days in the assignee's own timezone, so the date label
        // and the working window are always the same local day.
        $cursor = $from->setTimezone($tz)->startOfDay();
        $last = $to->setTimezone($tz)->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $localDate = $cursor->format('Y-m-d');
            $slots = [];

            foreach ($this->windowsFor($cursor, $rules, $exceptions) as [$winStart, $winEnd]) {
                // Step candidate starts by the slot length across the window.
                for ($s = $winStart; $s->addMinutes($length)->lessThanOrEqualTo($winEnd); $s = $s->addMinutes($length)) {
                    $startUtc = $s->utc();

                    if ($startUtc->lessThan($earliest)) {
                        continue;
                    }

                    $occStart = $startUtc->subMinutes($bufBefore);
                    $occEnd = $startUtc->addMinutes($length + $bufAfter);

                    if ($this->overlapsAny($occStart, $occEnd, $busy)) {
                        continue;
                    }

                    $slots[] = [
                        'start' => $startUtc->format('Y-m-d\TH:i:s\Z'),
                        'label' => $s->format('g:i A'),
                    ];
                }
            }

            if ($slots) {
                $out[] = [
                    'date' => $localDate,
                    'label' => $cursor->format('D, M j'),
                    'slots' => $slots,
                ];
            }

            $cursor = $cursor->addDay();
        }

        return $out;
    }

    /**
     * Re-check a specific start instant at booking time, so a stale page or a
     * tampered value can never double-book. Returns true only if the slot is
     * still one this service would offer.
     */
    public function isSlotOpen(BookingType $type, CarbonImmutable $startUtc, ?int $excludeWorkOrderId = null): bool
    {
        $assignee = $type->assignee;
        if (! $assignee) {
            return false;
        }

        $tz = $assignee->effectiveTimezone();
        $day = $startUtc->setTimezone($tz)->startOfDay();

        if ($startUtc->lessThan(CarbonImmutable::now()->addMinutes(self::LEAD_MINUTES))) {
            return false;
        }

        [$rules, $exceptions] = $this->schedule($assignee);
        $length = (int) $type->duration_minutes ?: 60;

        // The instant must fall on an exact step inside a working window.
        $onGrid = false;
        foreach ($this->windowsFor($day, $rules, $exceptions) as [$winStart, $winEnd]) {
            for ($s = $winStart; $s->addMinutes($length)->lessThanOrEqualTo($winEnd); $s = $s->addMinutes($length)) {
                if ($s->utc()->equalTo($startUtc)) {
                    $onGrid = true;
                    break 2;
                }
            }
        }
        if (! $onGrid) {
            return false;
        }

        $occStart = $startUtc->subMinutes((int) $type->buffer_before_minutes);
        $occEnd = $startUtc->addMinutes($length + (int) $type->buffer_after_minutes);
        $busy = $this->busyIntervals($assignee, $occStart->subDay(), $occEnd->addDay(), $excludeWorkOrderId);

        return ! $this->overlapsAny($occStart, $occEnd, $busy);
    }

    /**
     * Busy intervals for the assignee across the range: connected-calendar busy
     * blocks plus their live (non-cancelled) work orders.
     *
     * @return array<int, array{0:CarbonImmutable, 1:CarbonImmutable}>
     */
    private function busyIntervals(User $assignee, CarbonImmutable $from, CarbonImmutable $to, ?int $excludeWorkOrderId = null): array
    {
        $intervals = [];

        $connectionIds = CalendarConnection::where('user_id', $assignee->id)->pluck('id');
        if ($connectionIds->isNotEmpty()) {
            $blocks = CalendarBusyBlock::whereIn('calendar_connection_id', $connectionIds)
                ->where('ends_at', '>', $from)
                ->where('starts_at', '<', $to)
                ->get(['starts_at', 'ends_at']);

            foreach ($blocks as $b) {
                $intervals[] = [CarbonImmutable::parse($b->starts_at), CarbonImmutable::parse($b->ends_at)];
            }
        }

        $orders = WorkOrder::where('assigned_user_id', $assignee->id)
            ->whereIn('status', ['scheduled', 'in_progress', 'on_hold'])
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$from, $to])
            ->when($excludeWorkOrderId, fn ($q) => $q->where('id', '!=', $excludeWorkOrderId))
            ->get(['scheduled_at', 'duration_minutes']);

        foreach ($orders as $wo) {
            $start = CarbonImmutable::parse($wo->scheduled_at);
            $intervals[] = [$start, $start->addMinutes((int) $wo->duration_minutes ?: 60)];
        }

        return $intervals;
    }

    /**
     * Working windows for one local day (a timezone-aware start-of-day in the
     * assignee's zone): a date exception wins over the weekly rules; a day-off
     * exception yields no windows.
     *
     * @param  \Illuminate\Support\Collection<int, AvailabilityRule>  $rules
     * @param  \Illuminate\Support\Collection<int, AvailabilityException>  $exceptions
     * @return array<int, array{0:CarbonImmutable, 1:CarbonImmutable}>
     */
    private function windowsFor(CarbonImmutable $localDay, $rules, $exceptions): array
    {
        $dateKey = $localDay->format('Y-m-d');

        $exception = $exceptions->firstWhere(fn (AvailabilityException $e) => CarbonImmutable::parse($e->date)->format('Y-m-d') === $dateKey);

        if ($exception) {
            if (! $exception->is_available) {
                return [];
            }
            if ($exception->start_time && $exception->end_time) {
                return [$this->window($localDay, $exception->start_time, $exception->end_time)];
            }
        }

        $weekday = (int) $localDay->dayOfWeek; // 0=Sun..6=Sat, matches AvailabilityRule.
        $windows = [];
        foreach ($rules->where('weekday', $weekday) as $rule) {
            $windows[] = $this->window($localDay, $rule->start_time, $rule->end_time);
        }

        return $windows;
    }

    /** Build a [start, end] pair of tz-aware instants for a day + H:i(:s) times. */
    private function window(CarbonImmutable $localDay, string $start, string $end): array
    {
        return [
            $localDay->setTimeFromTimeString($start),
            $localDay->setTimeFromTimeString($end),
        ];
    }

    /** @return array{0:\Illuminate\Support\Collection, 1:\Illuminate\Support\Collection} */
    private function schedule(User $assignee): array
    {
        return [
            AvailabilityRule::where('user_id', $assignee->id)->where('is_active', true)->get(),
            AvailabilityException::where('user_id', $assignee->id)->get(),
        ];
    }

    /** @param array<int, array{0:CarbonImmutable, 1:CarbonImmutable}> $intervals */
    private function overlapsAny(CarbonImmutable $start, CarbonImmutable $end, array $intervals): bool
    {
        foreach ($intervals as [$bStart, $bEnd]) {
            if ($start->lessThan($bEnd) && $bStart->lessThan($end)) {
                return true;
            }
        }

        return false;
    }
}
