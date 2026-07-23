<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The triage inbox. Service requests are read, converted into a ticket and/or a
 * work order, or closed. There is no create/edit here: requests arrive from the
 * public form (or are logged elsewhere) and this screen only acts on them.
 */
class ServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = ServiceRequest::with(['customer', 'service'])
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate((int) config('shop.rows_per_page', 25))
            ->withQueryString();

        $filters = $request->only(['q', 'status']);

        return view('admin.service-requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'tabs' => $this->indexTabs($filters),
        ]);
    }

    /** Status tabs, each resolved to its URL and active state. */
    private function indexTabs(array $filters): array
    {
        $counts = [
            'all' => ServiceRequest::count(),
            'new' => ServiceRequest::where('status', 'new')->count(),
            'triaged' => ServiceRequest::where('status', 'triaged')->count(),
            'converted' => ServiceRequest::where('status', 'converted')->count(),
            'closed' => ServiceRequest::where('status', 'closed')->count(),
        ];

        $definitions = [
            ['key' => 'all', 'label' => 'All', 'status' => null],
            ['key' => 'new', 'label' => 'New', 'status' => 'new'],
            ['key' => 'triaged', 'label' => 'Triaged', 'status' => 'triaged'],
            ['key' => 'converted', 'label' => 'Converted', 'status' => 'converted'],
            ['key' => 'closed', 'label' => 'Closed', 'status' => 'closed'],
        ];

        $active = $filters['status'] ?? null;
        $search = array_filter(['q' => $filters['q'] ?? null]);

        return array_map(fn ($tab) => [
            'label' => $tab['label'],
            'count' => $counts[$tab['key']] ?? 0,
            'active' => $tab['status'] === $active,
            'href' => route('service-requests.index', array_merge(
                $tab['status'] ? ['status' => $tab['status']] : [],
                $search
            )),
        ], $definitions);
    }

    public function show(ServiceRequest $serviceRequest)
    {
        $serviceRequest->load(['customer', 'service', 'attachments', 'ticket', 'workOrder', 'activities.user']);

        return view('admin.service-requests.show', [
            'serviceRequest' => $serviceRequest,
            'addressLines' => $this->addressLines($serviceRequest->address),
            'tabs' => $this->indexTabs(['status' => $serviceRequest->status]),
        ]);
    }

    /** Spawn a ticket from the request and mark the request converted. */
    public function convertToTicket(ServiceRequest $serviceRequest)
    {
        if ($serviceRequest->ticket_id) {
            return redirect()
                ->route('tickets.show', $serviceRequest->ticket_id)
                ->with('warning', 'This request already has a ticket.');
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

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Ticket '.$ticket->number.' created from this request.');
    }

    /** Spawn a work order from the request and mark the request converted. */
    public function convertToWorkOrder(ServiceRequest $serviceRequest)
    {
        if ($serviceRequest->work_order_id) {
            return redirect()
                ->route('work-orders.show', $serviceRequest->work_order_id)
                ->with('warning', 'This request already has a work order.');
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

        return redirect()
            ->route('work-orders.show', $workOrder)
            ->with('status', 'Work order '.$workOrder->number.' created from this request.');
    }

    /** Seed a draft quote from the request and open it for editing. */
    public function convertToQuote(ServiceRequest $serviceRequest)
    {
        $quote = DB::transaction(function () use ($serviceRequest) {
            $quote = Quote::create([
                'customer_id' => $serviceRequest->customer_id,
                'service_request_id' => $serviceRequest->id,
                'created_by' => auth()->id(),
                'title' => $serviceRequest->subject,
                'message' => $serviceRequest->description,
                'status' => 'draft',
                'address' => $serviceRequest->address,
                'currency' => config('shop.currency', 'USD'),
            ]);

            $quote->recordActivity('created', 'Created From Service Request '.$serviceRequest->number);
            $serviceRequest->recordActivity('note', 'Quote '.$quote->number.' drafted from this request', ['quote_id' => $quote->id]);

            return $quote;
        });

        return redirect()
            ->route('quotes.edit', $quote)
            ->with('status', 'Quote '.$quote->number.' drafted from this request. Add line items and send it.');
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

        return back()->with('status', 'Request closed.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = ServiceRequest::whereIn('id', $ids)->delete();

        return back()->with('status', "Deleted {$count} request(s).");
    }

    /** Flatten a stored address into printable lines. */
    private function addressLines(?array $address): array
    {
        if (! $address) {
            return [];
        }

        $city = trim(
            ($address['city'] ?? '')
            .(! empty($address['state']) ? ', '.$address['state'] : '')
            .' '.($address['postcode'] ?? '')
        );

        return array_values(array_filter([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            $city,
            $address['country'] ?? null,
        ]));
    }
}
