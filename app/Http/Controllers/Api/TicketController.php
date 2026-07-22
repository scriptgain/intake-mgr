<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketReplyResource;
use App\Http\Resources\TicketResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The service-desk conversation over the API. Mirrors Admin\TicketController:
 * reply (public or internal note), change status, reassign, spawn work orders.
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = Ticket::with(['customer', 'assignee'])
            ->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
            ->when($request->filled('assigned_user_id'), fn ($q) => $q->where('assigned_user_id', $request->integer('assigned_user_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->latest()
            ->paginate($this->perPage($request));

        return TicketResource::collection($tickets);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $ticket = DB::transaction(function () use ($data) {
            $ticket = Ticket::create(array_merge($data, ['status' => $data['status'] ?? 'open']));
            $ticket->recordActivity('created', 'Ticket Created');

            return $ticket;
        });

        return (new TicketResource($ticket->load(['customer', 'assignee'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Ticket $ticket)
    {
        return new TicketResource($ticket->load([
            'customer', 'assignee', 'serviceRequest', 'project',
            'replies', 'workOrders', 'attachments', 'activities',
        ]));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $this->validated($request, $ticket);

        $ticket->update($data);

        return new TicketResource($ticket->load(['customer', 'assignee']));
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();

        return response()->noContent();
    }

    /** List the thread. Internal notes are included only when explicitly asked. */
    public function replies(Request $request, Ticket $ticket)
    {
        $replies = $ticket->replies()
            ->when(! $request->boolean('include_internal'), fn ($q) => $q->public())
            ->get();

        return TicketReplyResource::collection($replies);
    }

    /** Post a staff reply (or internal note) to the thread. */
    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $isInternal = (bool) ($data['is_internal'] ?? false);

        $reply = DB::transaction(function () use ($ticket, $data, $isInternal, $request) {
            $reply = TicketReply::create([
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

            return $reply;
        });

        return (new TicketReplyResource($reply))->response()->setStatusCode(201);
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

        return new TicketResource($ticket->load(['customer', 'assignee']));
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

        return new TicketResource($ticket->load(['customer', 'assignee']));
    }

    /** Spawn a work order from this ticket. */
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
                'title' => ($data['title'] ?? null) ?: $ticket->subject,
                'status' => 'scheduled',
                'currency' => config('shop.currency', 'USD'),
            ]);

            $ticket->recordActivity('created', 'Work Order '.$workOrder->number.' Created', ['work_order_id' => $workOrder->id]);
            $workOrder->recordActivity('created', 'Created From Ticket '.$ticket->number);

            return $workOrder;
        });

        return (new WorkOrderResource($workOrder))->response()->setStatusCode(201);
    }

    private function validated(Request $request, ?Ticket $ticket = null): array
    {
        $required = $ticket ? 'sometimes' : 'required';

        return $request->validate([
            'subject' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)],
            'status' => ['nullable', Rule::in(Ticket::STATUSES)],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_request_id' => ['nullable', 'integer', 'exists:service_requests,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(
            max(1, (int) $request->query('per_page', config('api.per_page', 25))),
            (int) config('api.max_per_page', 100)
        );
    }
}
