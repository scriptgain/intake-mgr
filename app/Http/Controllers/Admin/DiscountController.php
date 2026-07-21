<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Discount;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.discounts.index', [
            'discounts' => Discount::withCount('redemptions')
                ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%'.$request->string('q').'%'))
                ->latest()
                ->paginate((int) config('shop.rows_per_page', 25))
                ->withQueryString(),
            'filters' => $request->only('q'),
        ]);
    }

    public function create()
    {
        return view('admin.discounts.create', $this->formData(new Discount([
            'type' => 'percentage',
            'applies_to' => 'all',
            'is_active' => true,
        ])));
    }

    public function store(Request $request)
    {
        $discount = Discount::create($this->validated($request));

        return redirect()->route('discounts.edit', $discount)->with('status', 'Discount created.');
    }

    public function show(Discount $discount)
    {
        return redirect()->route('discounts.edit', $discount);
    }

    public function edit(Discount $discount)
    {
        return view('admin.discounts.edit', $this->formData($discount));
    }

    public function update(Request $request, Discount $discount)
    {
        $discount->update($this->validated($request, $discount));

        return back()->with('status', 'Discount saved.');
    }

    public function destroy(Discount $discount)
    {
        $discount->delete();

        return redirect()->route('discounts.index')->with('status', 'Discount deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = Discount::whereIn('id', $ids)->delete();

        return back()->with('status', "Deleted {$count} discount(s).");
    }

    private function formData(Discount $discount): array
    {
        return [
            'discount' => $discount,
            'collections' => Collection::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'selectedTargets' => $discount->target_ids ?? [],
            // Pre-formatted for the form's value inputs, so Blade prints strings.
            'valueInput' => match ($discount->type) {
                'fixed_amount' => $discount->value ? Money::format((int) $discount->value, false) : '',
                'percentage' => $discount->value ? rtrim(rtrim(number_format($discount->value / 100, 2), '0'), '.') : '',
                default => '',
            },
            'minSubtotalInput' => $discount->min_subtotal_cents
                ? Money::format((int) $discount->min_subtotal_cents, false)
                : '',
        ];
    }

    private function validated(Request $request, ?Discount $discount = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('discounts', 'code')->ignore($discount?->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(Discount::TYPES)],
            'value' => ['nullable', 'string'],
            'applies_to' => ['required', Rule::in(['all', 'collections', 'products'])],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['integer'],
            'min_subtotal' => ['nullable', 'string'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        // Percentages are stored as basis points, fixed amounts as cents — both
        // integers, so no float ever enters the money path.
        $value = match ($data['type']) {
            'percentage' => Money::percentToBps($data['value'] ?? 0),
            'fixed_amount' => Money::parse($data['value'] ?? null) ?? 0,
            default => 0,
        };

        return [
            'code' => $data['code'],
            'title' => $data['title'] ?? null,
            'type' => $data['type'],
            'value' => $value,
            'applies_to' => $data['applies_to'],
            'target_ids' => $data['applies_to'] === 'all' ? null : ($data['target_ids'] ?? []),
            'min_subtotal_cents' => Money::parse($data['min_subtotal'] ?? null),
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'once_per_customer' => $request->boolean('once_per_customer'),
        ];
    }
}
