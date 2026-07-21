<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Order::with('items')
                ->search($request->query('q'))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->when($request->filled('financial_status'), fn ($q) => $q->where('financial_status', $request->query('financial_status')))
                ->latest()
                ->paginate((int) $request->query('per_page', 25))
        );
    }

    public function show(Order $order)
    {
        return response()->json($order->load(['items', 'events', 'fulfillments', 'customer']));
    }

    /**
     * Limited to the status axes and staff note. Money is never writable over
     * the API — totals are what the customer was actually charged.
     */
    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(Order::STATUSES)],
            'fulfillment_status' => ['nullable', Rule::in(Order::FULFILLMENT_STATUSES)],
            'staff_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $order->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json($order);
    }
}
