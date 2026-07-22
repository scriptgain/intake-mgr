<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingTypeResource;
use App\Models\BookingType;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A named kind of appointment: duration, buffers, optional price and technician.
 * Mirrors Admin\BookingTypeController; the price is parsed from a decimal string
 * into integer cents (never accepted as cents directly).
 */
class BookingTypeController extends Controller
{
    public function index(Request $request)
    {
        $active = $request->filled('is_active') ? $request->boolean('is_active') : null;

        $bookingTypes = BookingType::with('assignee')
            ->search($request->query('q'))
            ->when($active !== null, fn ($q) => $q->where('is_active', $active))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return BookingTypeResource::collection($bookingTypes);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $bookingType = DB::transaction(fn () => BookingType::create($this->attributes($data)));

        return (new BookingTypeResource($bookingType->load('assignee')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(BookingType $bookingType)
    {
        return new BookingTypeResource($bookingType->load('assignee'));
    }

    public function update(Request $request, BookingType $bookingType)
    {
        $data = $this->validated($request, $bookingType);

        DB::transaction(fn () => $bookingType->forceFill($this->attributes($data, $bookingType))->save());

        return new BookingTypeResource($bookingType->load('assignee'));
    }

    public function destroy(BookingType $bookingType)
    {
        $bookingType->delete();

        return response()->noContent();
    }

    /* ---- Helpers ------------------------------------------------------- */

    private function validated(Request $request, ?BookingType $bookingType = null): array
    {
        $required = $bookingType ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => [$required, 'integer', 'min:5'],
            'buffer_before_minutes' => ['nullable', 'integer', 'min:0'],
            'buffer_after_minutes' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'string', 'max:32'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * Shape validated input into model attributes, parsing the price into cents.
     * On update, only the keys actually supplied are applied so a partial patch
     * never clobbers an unsent field.
     */
    private function attributes(array $data, ?BookingType $bookingType = null): array
    {
        $current = $bookingType ?? new BookingType([
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'price_cents' => 0,
            'is_active' => false,
            'position' => 0,
        ]);

        $attrs = [];

        if (array_key_exists('name', $data)) {
            $attrs['name'] = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $attrs['description'] = $data['description'];
        }
        if (array_key_exists('duration_minutes', $data)) {
            $attrs['duration_minutes'] = (int) $data['duration_minutes'];
        }
        if (array_key_exists('buffer_before_minutes', $data)) {
            $attrs['buffer_before_minutes'] = (int) $data['buffer_before_minutes'];
        }
        if (array_key_exists('buffer_after_minutes', $data)) {
            $attrs['buffer_after_minutes'] = (int) $data['buffer_after_minutes'];
        }
        if (array_key_exists('price', $data)) {
            $attrs['price_cents'] = Money::parse($data['price']) ?? 0;
        }
        if (array_key_exists('assigned_user_id', $data)) {
            $attrs['assigned_user_id'] = $data['assigned_user_id'];
        }
        if (array_key_exists('color', $data)) {
            $attrs['color'] = $data['color'];
        }
        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }
        if (array_key_exists('position', $data)) {
            $attrs['position'] = (int) $data['position'];
        }

        // A brand-new booking type still needs sane defaults for anything the
        // caller omitted (matching the admin create form's defaults).
        if (! $bookingType) {
            $attrs += [
                'buffer_before_minutes' => (int) $current->buffer_before_minutes,
                'buffer_after_minutes' => (int) $current->buffer_after_minutes,
                'price_cents' => (int) $current->price_cents,
                'is_active' => (bool) $current->is_active,
                'position' => (int) $current->position,
            ];
        }

        return $attrs;
    }

    private function perPage(Request $request): int
    {
        return min(
            max(1, (int) $request->query('per_page', config('api.per_page', 25))),
            (int) config('api.max_per_page', 100)
        );
    }
}
