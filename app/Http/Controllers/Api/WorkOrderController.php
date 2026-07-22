<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkOrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\WorkOrder;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Scheduled service work over the API. Mirrors Admin\WorkOrderController: full
 * CRUD plus status, complete (which can generate an invoice Order), cancel and
 * reschedule. Line items freeze the service name and price at scheduling time.
 */
class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $workOrders = WorkOrder::with(['customer', 'assignee'])
            ->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when(
                $request->boolean('upcoming'),
                fn ($q) => $q->upcoming(),
                fn ($q) => $q->latest()
            )
            ->paginate($this->perPage($request));

        return WorkOrderResource::collection($workOrders);
    }

    public function store(Request $request)
    {
        $data = $this->validateWorkOrder($request);

        $workOrder = DB::transaction(function () use ($data, $request) {
            $workOrder = WorkOrder::create([
                'customer_id' => $data['customer_id'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'address' => $this->addressFrom($request),
                'currency' => config('shop.currency', 'USD'),
            ]);

            $this->syncItems($workOrder, $request->input('items', []));
            $workOrder->recordActivity('created', 'Work Order Created');

            return $workOrder;
        });

        return (new WorkOrderResource($workOrder->load(['customer', 'assignee', 'items'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(WorkOrder $workOrder)
    {
        return new WorkOrderResource($workOrder->load([
            'customer', 'assignee', 'items', 'invoice', 'activities',
        ]));
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        $data = $this->validateWorkOrder($request);

        DB::transaction(function () use ($data, $request, $workOrder) {
            $workOrder->forceFill([
                'customer_id' => $data['customer_id'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'address' => $this->addressFrom($request),
            ])->save();

            $this->syncItems($workOrder, $request->input('items', []));
            $workOrder->recordActivity('note', 'Work Order Updated');
        });

        return new WorkOrderResource($workOrder->load(['customer', 'assignee', 'items']));
    }

    public function destroy(WorkOrder $workOrder)
    {
        $workOrder->delete();

        return response()->noContent();
    }

    public function status(Request $request, WorkOrder $workOrder)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(WorkOrder::STATUSES)],
        ]);

        DB::transaction(function () use ($workOrder, $data) {
            $attrs = ['status' => $data['status']];

            if ($data['status'] === 'in_progress' && ! $workOrder->started_at) {
                $attrs['started_at'] = now();
            }

            $workOrder->forceFill($attrs)->save();
            $workOrder->recordActivity('status', 'Status Changed To '.$workOrder->status_label);
        });

        return new WorkOrderResource($workOrder->load(['customer', 'assignee', 'items']));
    }

    /**
     * Mark the work order completed. If it has a billable subtotal, generate an
     * invoice (an Order) from its line items and link the two together.
     */
    public function complete(WorkOrder $workOrder)
    {
        DB::transaction(function () use ($workOrder) {
            if ($workOrder->status === 'completed') {
                return;
            }

            $workOrder->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();

            $workOrder->recordActivity('completed', 'Work Order Completed');

            $workOrder->recalcTotals();
            $workOrder->refresh();

            if ($workOrder->subtotal_cents <= 0) {
                return;
            }

            $customer = $workOrder->customer;

            $invoice = Order::create([
                'number' => Order::nextNumber(),
                'customer_id' => $workOrder->customer_id,
                'email' => $customer?->email ?? '',
                'phone' => $customer?->phone,
                'status' => 'open',
                'financial_status' => 'pending',
                'fulfillment_status' => 'fulfilled',
                'currency' => $workOrder->currency,
                'subtotal_cents' => $workOrder->subtotal_cents,
                'discount_cents' => 0,
                'shipping_cents' => 0,
                'tax_cents' => 0,
                'total_cents' => $workOrder->subtotal_cents,
                'payment_gateway' => Setting::get('default_gateway', 'stripe'),
                'work_order_id' => $workOrder->id,
                'project_id' => $workOrder->project_id,
            ]);

            foreach ($workOrder->items as $item) {
                OrderItem::create([
                    'order_id' => $invoice->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'total_cents' => $item->total_cents,
                    'requires_shipping' => false,
                ]);
            }

            $workOrder->forceFill(['invoice_order_id' => $invoice->id])->save();

            $invoice->recordEvent('placed', 'Invoice Generated From Work Order '.$workOrder->number);
            $workOrder->recordActivity('payment', 'Invoice '.$invoice->number.' Generated', ['order_id' => $invoice->id]);
        });

        return new WorkOrderResource($workOrder->load(['customer', 'assignee', 'items', 'invoice', 'activities']));
    }

    public function cancel(Request $request, WorkOrder $workOrder)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($workOrder, $data) {
            $workOrder->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $data['reason'] ?? null,
            ])->save();

            $workOrder->recordActivity(
                'cancelled',
                'Work Order Cancelled'.(! empty($data['reason']) ? ': '.$data['reason'] : '')
            );
        });

        return new WorkOrderResource($workOrder->load(['customer', 'assignee', 'items']));
    }

    public function reschedule(Request $request, WorkOrder $workOrder)
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($workOrder, $data) {
            $attrs = ['scheduled_at' => $data['scheduled_at']];
            if (array_key_exists('duration_minutes', $data) && $data['duration_minutes'] !== null) {
                $attrs['duration_minutes'] = (int) $data['duration_minutes'];
            }

            $workOrder->forceFill($attrs)->save();
            $workOrder->recordActivity('scheduled', 'Work Order Rescheduled');
        });

        return new WorkOrderResource($workOrder->load(['customer', 'assignee', 'items']));
    }

    /* ---- Helpers ------------------------------------------------------- */

    private function validateWorkOrder(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(WorkOrder::STATUSES)],
            'scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'address' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.service_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'string', 'max:32'],
        ]);
    }

    /** Rebuild the line items, computing each total in integer cents. */
    private function syncItems(WorkOrder $workOrder, array $rows): void
    {
        $workOrder->items()->delete();

        foreach ($rows as $row) {
            $serviceId = ! empty($row['service_id']) ? (int) $row['service_id'] : null;
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '' && $serviceId) {
                $name = Product::find($serviceId)?->name ?? '';
            }

            if ($name === '') {
                continue;
            }

            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $unit = Money::parse($row['unit_price'] ?? null) ?? 0;

            $workOrder->items()->create([
                'service_id' => $serviceId,
                'name' => $name,
                'description' => $row['description'] ?? null,
                'quantity' => $qty,
                'unit_price_cents' => $unit,
                'total_cents' => $unit * $qty,
            ]);
        }

        $workOrder->recalcTotals();
    }

    private function addressFrom(Request $request): ?array
    {
        $address = array_filter([
            'line1' => $request->input('address.line1'),
            'line2' => $request->input('address.line2'),
            'city' => $request->input('address.city'),
            'state' => $request->input('address.state'),
            'postcode' => $request->input('address.postcode'),
        ], fn ($v) => filled($v));

        return $address ?: null;
    }

    private function perPage(Request $request): int
    {
        return min(
            max(1, (int) $request->query('per_page', config('api.per_page', 25))),
            (int) config('api.max_per_page', 100)
        );
    }
}
