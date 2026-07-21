<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRule;
use App\Support\Money;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.taxes.index', [
            'rules' => TaxRule::when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
                ->orderByDesc('priority')
                ->orderBy('country')
                ->orderBy('state')
                ->paginate((int) config('shop.rows_per_page', 25))
                ->withQueryString(),
            'filters' => $request->only('q'),
            'taxMode' => config('shop.tax_mode'),
        ]);
    }

    public function create()
    {
        return view('admin.taxes.create', [
            'taxRule' => new TaxRule(['country' => 'US', 'is_active' => true, 'tax_class' => 'standard']),
            'rateInput' => '',
        ]);
    }

    public function store(Request $request)
    {
        TaxRule::create($this->validated($request));

        return redirect()->route('taxes.index')->with('status', 'Tax rule created.');
    }

    public function show(TaxRule $taxRule)
    {
        return redirect()->route('taxes.edit', $taxRule);
    }

    public function edit(TaxRule $taxRule)
    {
        return view('admin.taxes.edit', [
            'taxRule' => $taxRule,
            'rateInput' => rtrim(rtrim(number_format($taxRule->rate_bps / 100, 2), '0'), '.'),
        ]);
    }

    public function update(Request $request, TaxRule $taxRule)
    {
        $taxRule->update($this->validated($request));

        return back()->with('status', 'Tax rule saved.');
    }

    public function destroy(TaxRule $taxRule)
    {
        $taxRule->delete();

        return redirect()->route('taxes.index')->with('status', 'Tax rule deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = TaxRule::whereIn('id', $ids)->delete();

        return back()->with('status', "Deleted {$count} tax rule(s).");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:64'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'rate' => ['required', 'string'],
            'tax_class' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $data['name'],
            'country' => strtoupper($data['country']),
            'state' => $data['state'] ? strtoupper($data['state']) : null,
            'postcode' => $data['postcode'] ?? null,
            // Stored as basis points: 7.25% -> 725.
            'rate_bps' => Money::percentToBps($data['rate']),
            'tax_class' => $data['tax_class'] ?: 'standard',
            'priority' => (int) ($data['priority'] ?? 0),
            'applies_to_shipping' => $request->boolean('applies_to_shipping'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
