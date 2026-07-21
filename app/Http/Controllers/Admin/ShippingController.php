<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Shipping zones and the rates inside them. A zone edit page owns its rates,
 * so rate CRUD lives here rather than in a controller of its own.
 */
class ShippingController extends Controller
{
    public function index()
    {
        return view('admin.shipping.index', [
            'zones' => ShippingZone::with('rates')->orderBy('position')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.shipping.create', [
            'zone' => new ShippingZone(['is_active' => true, 'countries' => ['US']]),
        ]);
    }

    public function store(Request $request)
    {
        $zone = ShippingZone::create($this->validatedZone($request));

        return redirect()->route('shipping.edit', $zone)->with('status', 'Shipping zone created. Add a rate below.');
    }

    public function edit(ShippingZone $zone)
    {
        return view('admin.shipping.edit', [
            'zone' => $zone->load('rates'),
            'rateTypes' => ShippingRate::TYPES,
        ]);
    }

    public function update(Request $request, ShippingZone $zone)
    {
        $zone->update($this->validatedZone($request));

        return back()->with('status', 'Shipping zone saved.');
    }

    public function destroy(ShippingZone $zone)
    {
        $zone->delete(); // rates cascade

        return redirect()->route('shipping.index')->with('status', 'Shipping zone deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = ShippingZone::whereIn('id', $ids)->delete();

        return back()->with('status', "Deleted {$count} zone(s).");
    }

    /* ---- Rates -------------------------------------------------------- */

    public function storeRate(Request $request, ShippingZone $zone)
    {
        $zone->rates()->create($this->validatedRate($request) + [
            'position' => (int) $zone->rates()->max('position') + 1,
        ]);

        return back()->with('status', 'Rate added.');
    }

    public function updateRate(Request $request, ShippingRate $rate)
    {
        $rate->update($this->validatedRate($request));

        return back()->with('status', 'Rate saved.');
    }

    public function destroyRate(ShippingRate $rate)
    {
        $rate->delete();

        return back()->with('status', 'Rate removed.');
    }

    /* ------------------------------------------------------------------- */

    private function validatedZone(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Comma-separated ISO-2 codes, or * for the catch-all zone.
            'countries' => ['required', 'string', 'max:2000'],
            'states' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $data['name'],
            'countries' => $this->splitCodes($data['countries']),
            'states' => $data['states'] ? $this->splitCodes($data['states']) : null,
            'position' => (int) ($data['position'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function validatedRate(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(ShippingRate::TYPES)],
            'price' => ['nullable', 'string'],
            'min_value' => ['nullable', 'numeric', 'min:0'],
            'max_value' => ['nullable', 'numeric', 'min:0'],
            'free_above' => ['nullable', 'string'],
        ]);

        // Weight bands are grams (entered as grams); price bands are money.
        $isPriceBand = $data['type'] === 'price';

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'price_cents' => Money::parse($data['price'] ?? null) ?? 0,
            'min_value' => $this->band($data['min_value'] ?? null, $isPriceBand),
            'max_value' => $this->band($data['max_value'] ?? null, $isPriceBand),
            'free_above_cents' => Money::parse($data['free_above'] ?? null),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function band(mixed $value, bool $isMoney): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $isMoney ? Money::parse((string) $value) : (int) $value;
    }

    /** "US, CA, GB" -> ['US','CA','GB'] */
    private function splitCodes(string $input): array
    {
        return collect(preg_split('/[\s,]+/', strtoupper(trim($input))))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
