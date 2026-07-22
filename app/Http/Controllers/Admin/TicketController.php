<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The service-desk conversation. Staff reply (publicly or with internal notes),
 * change status and priority, reassign, and spawn work orders from a ticket.
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = Ticket::with(['customer', 'assignee'])
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('assignee'), fn ($q) => $q->where('assigned_user_id', $request->integer('assignee')))
            ->latest()
            ->paginate((int) config('shop.rows_per_page', 25))
            ->withQueryString();

        $filters = $request->only(['q', 'status', 'priority', 'assignee']);

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'tabs' => $this->indexTabs($filters),
            'agents' => User::orderBy('name')->get(),
        ]);
    }

    private function indexTabs(array $filters): array
    {
        $counts = [
            'all' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'pending' => Ticket::where('status', 'pending')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'resolved' => Ticket::where('status', 'resolved')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
        ];

        $definitions = [
            ['key' => 'all', 'label' => 'All', 'status' => null],
            ['key' => 'open', 'label' => 'Open', 'status' => 'open'],
            ['key' => 'pending', 'label' => 'Pending', 'status' => 'pending'],
            ['key' => 'in_progress', 'label' => 'In Progress', 'status' => 'in_progress'],
            ['key' => 'resolved', 'label' => 'Resolved', 'status' => 'resolved'],
            ['key' => 'closed', 'label' => 'Closed', 'status' => 'closed'],
        ];

        $active = $filters['status'] ?? null;
        $preserve = array_filter([
            'q' => $filters['q'] ?? null,
            'priority' => $filters['priority'] ?? null,
            'assignee' => $filters['assignee'] ?? null,
        ]);

        return array_map(fn ($tab) => [
            'label' => $tab['label'],
            'count' => $counts[$tab['key']] ?? 0,
            'active' => $tab['status'] === $active,
            'href' => route('tickets.index', array_merge(
                $tab['status'] ? ['status' => $tab['status']] : [],
                $preserve
            )),
        ], $definitions);
    }

    public function show(Ticket $ticket)
    {
        $ticket->load([
            'customer', 'assignee', 'serviceRequest', 'project',
            'replies.user', 'replies.customer',
            'workOrders', 'attachments', 'activities.user',
        ]);

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'agents' => User::orderBy('name')->get(),
        ]);
    }

    /** Post a reply to the thread. An internal note never reaches the customer. */
    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $isInternal = $request->boolean('is_internal');

        DB::transaction(function () use ($ticket, $data, $isInternal, $request) {
            TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'author_type' => 'staff',
                'author_name' => $request->user()->name,
                'body' => $data['body'],
                'is_internal' => $isInternal,
            ]);

            $ticket->forceFill([
                'last_reply_at' => now(),
                'last_reply_by' => 'staff',
            ])->save();

            $ticket->recordActivity(
                'reply',
                $isInternal ? 'Internal Note Added' : 'Staff Replied',
                ['internal' => $isInternal]
            );
        });

        return back()->with('status', $isInternal ? 'Internal note added.' : 'Reply sent.');
    }

    public function status(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Ticket::STATUSES)],
        ]);

        DB::transaction(function () use ($ticket, $data) {
            $attrs = ['status' => $data['status']];

            if ($data['status'] === 'resolved') {
                $attrs['resolved_at'] = now();
            } elseif ($data['status'] === 'closed') {
                $attrs['closed_at'] = now();
                $attrs['resolved_at'] = $ticket->resolved_at ?? now();
            }

            $ticket->forceFill($attrs)->save();
            $ticket->recordActivity('status', 'Status Changed To '.$ticket->status_label);
        });

        return back()->with('status', 'Ticket status updated.');
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($ticket, $data) {
            $ticket->forceFill(['assigned_user_id' => $data['assigned_user_id'] ?? null])->save();

            $name = $ticket->assigned_user_id
                ? (User::find($ticket->assigned_user_id)?->name ?? 'Agent')
                : null;

            $ticket->recordActivity('assigned', $name ? 'Assigned To '.$name : 'Unassigned');
        });

        return back()->with('status', 'Assignment updated.');
    }

    /** Spawn a work order from this ticket and jump to it. */
    public function workOrder(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $workOrder = DB::transaction(function () use ($ticket, $data) {
            $workOrder = WorkOrder::create([
                'customer_id' => $ticket->customer_id,
                'ticket_id' => $ticket->id,
                'project_id' => $ticket->project_id,
                'title' => $data['title'] ?: $ticket->subject,
                'status' => 'scheduled',
                'currency' => config('shop.currency', 'USD'),
            ]);

            $ticket->recordActivity('created', 'Work Order '.$workOrder->number.' Created', ['work_order_id' => $workOrder->id]);
            $workOrder->recordActivity('created', 'Created From Ticket '.$ticket->number);

            return $workOrder;
        });

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work order '.$workOrder->number.' created from this ticket.');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();

        return redirect()->route('tickets.index')->with('status', 'Ticket deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = Ticket::whereIn('id', $ids)->delete();

        return back()->with('status', "Deleted {$count} ticket(s).");
    }
}
