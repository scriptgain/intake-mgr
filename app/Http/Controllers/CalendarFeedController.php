<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Calendar\IcsBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves iCalendar feeds. The subscription feed is public but unguessable (the
 * token is the credential); the single-work-order download is admin-guarded by
 * its route. No OAuth involved — this is the universal "add to any calendar".
 */
class CalendarFeedController extends Controller
{
    public function __construct(private readonly IcsBuilder $ics)
    {
    }

    /** Per-staff subscription feed: /calendar/feed/{token}.ics */
    public function feed(string $token): Response
    {
        $user = User::where('calendar_feed_token', $token)->first();
        abort_if($user === null, 404);

        $workOrders = WorkOrder::query()
            ->where('assigned_user_id', $user->id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now()->subDays(60))
            ->with(['customer', 'assignee'])
            ->orderBy('scheduled_at')
            ->limit(1000)
            ->get();

        $body = $this->ics->calendar($workOrders, $user->name.' Schedule');

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="schedule.ics"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /** Add-to-Calendar download for a single work order (admin). */
    public function workOrderIcs(Request $request, WorkOrder $workOrder): Response
    {
        $workOrder->loadMissing(['customer', 'assignee']);
        $body = $this->ics->forWorkOrder($workOrder);

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$workOrder->number.'.ics"',
        ]);
    }
}
