<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingType;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A named kind of appointment: its duration, buffers, an optional price and an
 * optional default technician. These drive scheduling and availability slots.
 */
class BookingTypeController extends Controller
{
    public function index(Request $request)
    {
        $active = $request->filled('active') ? $request->boolean('active') : null;

        $bookingTypes = BookingType::with('assignee')
            ->search($request->string('q')->toString() ?: null)
            ->when($active !== null, fn ($q) => $q->where('is_active', $active))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate((int) config('shop.rows_per_page', 25))
            ->withQueryString();

        $filters = $request->only(['q', 'active']);

        return view('admin.booking-types.index', [
            'bookingTypes' => $bookingTypes,
            'filters' => $filters,
            'tabs' => $this->indexTabs($filters),
        ]);
    }

    private function indexTabs(array $filters): array
    {
        $counts = [
            'all' => BookingType::count(),
            'active' => BookingType::where('is_active', true)->count(),
            'inactive' => BookingType::where('is_active', false)->count(),
        ];

        $definitions = [
            ['key' => 'all', 'label' => 'All', 'active' => null],
            ['key' => 'active', 'label' => 'Active', 'active' => '1'],
            ['key' => 'inactive', 'label' => 'Inactive', 'active' => '0'],
        ];

        $current = array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== ''
            ? (string) (int) filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN)
            : null;

        $search = array_filter(['q' => $filters['q'] ?? null]);

        return array_map(fn ($tab) => [
            'label' => $tab['label'],
            'count' => $counts[$tab['key']] ?? 0,
            'active' => $tab['active'] === $current,
            'href' => route('booking-types.index', array_merge(
                $tab['active'] !== null ? ['active' => $tab['active']] : [],
                $search
            )),
        ], $definitions);
    }

    public function create()
    {
        return view('admin.booking-types.create', [
            'bookingType' => new BookingType([
                'duration_minutes' => 60,
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'is_active' => true,
                'position' => 0,
            ]),
            'agents' => User::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateBookingType($request);

        $bookingType = DB::transaction(fn () => BookingType::create($this->attributes($data)));

        return redirect()
            ->route('booking-types.index')
            ->with('status', 'Booking type "'.$bookingType->name.'" created.');
    }

    public function edit(BookingType $bookingType)
    {
        return view('admin.booking-types.edit', [
            'bookingType' => $bookingType,
            'agents' => User::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, BookingType $bookingType)
    {
        $data = $this->validateBookingType($request);

        DB::transaction(fn () => $bookingType->forceFill($this->attributes($data))->save());

        return redirect()
            ->route('booking-types.index')
            ->with('status', 'Booking type updated.');
    }

    public function destroy(BookingType $bookingType)
    {
        $bookingType->delete();

        return redirect()->route('booking-types.index')->with('status', 'Booking type deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = BookingType::whereIn('id', $ids)->delete();

        return back()->with('status', "Deleted {$count} booking type(s).");
    }

    /* ---- Helpers ------------------------------------------------------- */

    private function validateBookingType(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['required', 'integer', 'min:5'],
            'buffer_before_minutes' => ['required', 'integer', 'min:0'],
            'buffer_after_minutes' => ['required', 'integer', 'min:0'],
            'price' => ['nullable', 'string', 'max:32'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /** Shape validated input into model attributes, parsing the price into cents. */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => (int) $data['duration_minutes'],
            'buffer_before_minutes' => (int) $data['buffer_before_minutes'],
            'buffer_after_minutes' => (int) $data['buffer_after_minutes'],
            'price_cents' => Money::parse($data['price'] ?? null) ?? 0,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'color' => $data['color'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'position' => (int) ($data['position'] ?? 0),
        ];
    }
}
