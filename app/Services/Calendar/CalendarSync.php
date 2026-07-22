<?php

namespace App\Services\Calendar;

use App\Models\CalendarBusyBlock;
use App\Models\CalendarConnection;
use App\Models\CalendarSyncedEvent;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;

/**
 * The two-way sync engine.
 *
 * PUSH: a scheduled work order is mirrored onto its assignee's connected
 * calendars (create/update/delete the remote event, tracked in
 * calendar_synced_events). Best-effort and swallow-all: a provider outage must
 * never break saving a work order.
 *
 * PULL: external busy intervals are cached in calendar_busy_blocks so
 * availability reflects the technician's real calendar.
 */
class CalendarSync
{
    public function __construct(
        private readonly CalendarManager $manager,
        private readonly TokenRefresher $tokens,
    ) {
    }

    /** Mirror a work order onto every connected calendar of its assignee. */
    public function pushWorkOrder(WorkOrder $workOrder): void
    {
        $userId = $workOrder->assigned_user_id;
        if (! $userId) {
            return;
        }

        $connections = CalendarConnection::where('user_id', $userId)
            ->where('status', 'connected')
            ->get();

        foreach ($connections as $connection) {
            rescue(fn () => $this->pushToConnection($workOrder, $connection), null, false);
        }
    }

    private function pushToConnection(WorkOrder $workOrder, CalendarConnection $connection): void
    {
        $provider = $this->manager->for($connection);
        if (! $provider || ! $this->tokens->ensureFresh($connection)) {
            return;
        }

        $mapping = CalendarSyncedEvent::where('calendar_connection_id', $connection->id)
            ->where('work_order_id', $workOrder->id)
            ->first();

        // A cancelled or unscheduled work order should not occupy the calendar.
        $shouldExist = $workOrder->scheduled_at && $workOrder->status !== 'cancelled';

        if (! $shouldExist) {
            if ($mapping) {
                $provider->deleteEvent($connection, $mapping->remote_event_id);
                $mapping->delete();
            }

            return;
        }

        if ($mapping) {
            $result = $provider->updateEvent($connection, $workOrder, $mapping->remote_event_id);
            if ($result['ok'] ?? false) {
                $mapping->forceFill(['etag' => $result['etag'] ?? $mapping->etag, 'last_pushed_at' => now()])->save();
            }

            return;
        }

        $result = $provider->createEvent($connection, $workOrder);
        if (($result['ok'] ?? false) && ! empty($result['remote_event_id'])) {
            CalendarSyncedEvent::create([
                'calendar_connection_id' => $connection->id,
                'work_order_id' => $workOrder->id,
                'remote_event_id' => $result['remote_event_id'],
                'etag' => $result['etag'] ?? null,
                'last_pushed_at' => now(),
            ]);
        }
    }

    /** Refresh cached busy blocks for a single connection (next 60 days). */
    public function pullBusy(CalendarConnection $connection): bool
    {
        $provider = $this->manager->for($connection);
        if (! $provider || ! $this->tokens->ensureFresh($connection)) {
            return false;
        }

        $from = now();
        $to = now()->addDays(60);

        $result = rescue(fn () => $provider->listBusy($connection, $from, $to), ['ok' => false], false);

        if (! ($result['ok'] ?? false)) {
            $connection->forceFill(['last_error' => \Illuminate\Support\Str::limit($result['error'] ?? 'sync failed', 250)])->save();

            return false;
        }

        // Replace this connection's cached window.
        CalendarBusyBlock::where('calendar_connection_id', $connection->id)
            ->where('starts_at', '>=', $from->copy()->subDay())
            ->delete();

        foreach ($result['blocks'] ?? [] as $block) {
            CalendarBusyBlock::create([
                'calendar_connection_id' => $connection->id,
                'starts_at' => $block['starts_at'],
                'ends_at' => $block['ends_at'],
                'remote_event_id' => $block['remote_event_id'] ?? null,
            ]);
        }

        $connection->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

        return true;
    }

    /** Pull busy times for every connected calendar (used by calendar:sync). */
    public function syncAll(): int
    {
        $count = 0;
        CalendarConnection::where('status', 'connected')->each(function (CalendarConnection $c) use (&$count) {
            if (rescue(fn () => $this->pullBusy($c), false, false)) {
                $count++;
            }
        });

        return $count;
    }

    /** All connections belonging to a user (for the "Sync Now" button). */
    public function syncUser(int $userId): int
    {
        $count = 0;
        foreach (CalendarConnection::where('user_id', $userId)->where('status', 'connected')->get() as $c) {
            if (rescue(fn () => $this->pullBusy($c), false, false)) {
                $count++;
            }
        }

        return $count;
    }
}
