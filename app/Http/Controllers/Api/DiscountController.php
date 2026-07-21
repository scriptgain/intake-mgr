<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Discount::withCount('redemptions')->paginate((int) $request->query('per_page', 25))
        );
    }

    public function store(Request $request)
    {
        return response()->json(Discount::create($this->validated($request)), 201);
    }

    public function show(Discount $discount)
    {
        return response()->json($discount);
    }

    public function update(Request $request, Discount $discount)
    {
        $discount->update($this->validated($request, $discount));

        return response()->json($discount);
    }

    public function destroy(Discount $discount)
    {
        $discount->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Discount $discount = null): array
    {
        return $request->validate([
            'code' => [$discount ? 'sometimes' : 'required', 'string', 'max:64', Rule::unique('discounts', 'code')->ignore($discount?->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(Discount::TYPES)],
            // Basis points for a percentage, cents for a fixed amount.
            'value' => ['nullable', 'integer', 'min:0'],
            'applies_to' => ['nullable', Rule::in(['all', 'collections', 'products'])],
            'target_ids' => ['nullable', 'array'],
            'min_subtotal_cents' => ['nullable', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
