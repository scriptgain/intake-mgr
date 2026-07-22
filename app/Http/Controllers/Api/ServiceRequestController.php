<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestResource;
use App\Http\Resources\TicketResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The triage inbox over the API. Mirrors Admin\ServiceRequestController: requests
 * are read, converted into a ticket and/or a work order, or closed.
 */
class ServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = ServiceRequest::with(['customer', 'service'])
            ->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
            ->latest()
            ->paginate($this->perPage($request));

        return ServiceRequestResource::collection($requests);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $serviceRequest = DB::transaction(function () use ($data) {
            $serviceRequest = ServiceRequest::create(array_merge($data, [
                'status' => $data['status'] ?? 'new',
                'source' => $data['source'] ?? 'staff',
            ]));
            $serviceRequest->recordActivity('created', 'Service Request Created');

            return $serviceRequest;
        });

        return (new ServiceRequestResource($serviceRequest->load(['customer', 'service'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ServiceRequest $serviceRequest)
    {
        return new ServiceRequestResource(
            $serviceRequest->load(['customer', 'service', 'attachments', 'activities'])
        );
    }

    public function update(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $this->validated($request, $serviceRequest);

        $serviceRequest->update($data);

        return new ServiceRequestResource($serviceRequest->load(['customer', 'service']));
    }

    public function destroy(ServiceRequest $serviceRequest)
    {
        $serviceRequest->delete();

        return response()->noContent();
    }

    /** Spawn a ticket from the request and mark the request converted. */
    public function convertTicket(ServiceRequest $serviceRequest)
    {
        if ($serviceRequest->ticket_id) {
            return new TicketResource(Ticket::findOrFail($serviceRequest->ticket_id));
        }

        $ticket = DB::transaction(function () use ($serviceRequest) {
            $ticket = Ticket::create([
                'customer_id' => $serviceRequest->customer_id,
                'service_request_id' => $serviceRequest->id,
                'subject' => $serviceRequest->subject,
                'description' => $serviceRequest->description,
                'status' => 'open',
                'priority' => $serviceRequest->priority ?: 'normal',
            ]);

            $serviceRequest->forceFill([
                'ticket_id' => $ticket->id,
                'status' => 'converted',
            ])->save();

            $ticket->recordActivity('created', 'Created From Service Request '.$serviceRequest->number);
            $serviceRequest->recordActivity('converted', 'Converted To Ticket '.$ticket->number, ['ticket_id' => $ticket->id]);

            return $ticket;
        });

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    /** Spawn a work order from the request and mark the request converted. */
    public function convertWorkOrder(ServiceRequest $serviceRequest)
    {
        if ($serviceRequest->work_order_id) {
            return new WorkOrderResource(WorkOrder::findOrFail($serviceRequest->work_order_id));
        }

        $workOrder = DB::transaction(function () use ($serviceRequest) {
            $workOrder = WorkOrder::create([
                'customer_id' => $serviceRequest->customer_id,
                'title' => $serviceRequest->subject,
                'notes' => $serviceRequest->description,
                'status' => 'scheduled',
                'address' => $serviceRequest->address,
                'currency' => config('shop.currency', 'USD'),
            ]);

            $serviceRequest->forceFill([
                'work_order_id' => $workOrder->id,
                'status' => 'converted',
            ])->save();

            $workOrder->recordActivity('created', 'Created From Service Request '.$serviceRequest->number);
            $serviceRequest->recordActivity('converted', 'Converted To Work Order '.$workOrder->number, ['work_order_id' => $workOrder->id]);

            return $workOrder;
        });

        return (new WorkOrderResource($workOrder))->response()->setStatusCode(201);
    }

    public function close(ServiceRequest $serviceRequest)
    {
        DB::transaction(function () use ($serviceRequest) {
            $serviceRequest->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
            ])->save();

            $serviceRequest->recordActivity('status', 'Request Closed');
        });

        return new ServiceRequestResource($serviceRequest->load(['customer', 'service']));
    }

    private function validated(Request $request, ?ServiceRequest $serviceRequest = null): array
    {
        $required = $serviceRequest ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255'],
            'subject' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'array'],
            'source' => ['nullable', Rule::in(['web', 'phone', 'email', 'staff'])],
            'priority' => ['nullable', Rule::in(ServiceRequest::PRIORITIES)],
            'status' => ['nullable', Rule::in(ServiceRequest::STATUSES)],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'exists:products,id'],
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
