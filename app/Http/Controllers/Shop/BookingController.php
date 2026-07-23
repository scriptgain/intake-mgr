<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\BookingType;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use App\Services\Scheduling\SlotService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Public, self-serve scheduling.
 *
 *  - /book/{slug}          a shareable page per booking type; a visitor picks an
 *                          open slot and their details, which creates a scheduled
 *                          work order (and syncs to the assignee's calendar).
 *  - /schedule/{request}   a SIGNED link that schedules an existing service
 *                          request: pick a booking type + slot, and the request
 *                          becomes a scheduled work order.
 *
 * Open slots come from SlotService, and every booking re-checks the slot at write
 * time so a stale page can never double-book.
 */
class BookingController extends Controller
{
    /** How many days ahead the picker offers. */
    private const WINDOW_DAYS = 14;

    public function __construct(private readonly SlotService $slots)
    {
    }

    /** Public booking page for one type. */
    public function show(BookingType $bookingType)
    {
        abort_unless($bookingType->is_active, 404);

        return view('shop.book', [
            'type' => $bookingType,
            'days' => $bookingType->assignee ? $this->slots->availableSlots($bookingType, ...$this->window()) : [],
        ]);
    }

    /** Create the booking. */
    public function store(Request $request, BookingType $bookingType)
    {
        abort_unless($bookingType->is_active, 404);

        if (! $bookingType->assignee) {
            return back()->withErrors(['start' => 'Online booking is not available for this service yet. Please send us a request instead.']);
        }

        $data = $this->validateBooking($request);
        $startUtc = CarbonImmutable::parse($data['start'])->utc();

        if (! $this->slots->isSlotOpen($bookingType, $startUtc)) {
            return back()
                ->withErrors(['start' => 'Sorry, that time was just taken. Please pick another.'])
                ->withInput();
        }

        $customer = $this->customerFor($data);

        $workOrder = WorkOrder::create([
            'customer_id' => $customer->id,
            'assigned_user_id' => $bookingType->assigned_user_id,
            'booking_type_id' => $bookingType->id,
            'title' => $bookingType->name,
            'notes' => $data['notes'] ?? null,
            'status' => 'scheduled',
            'scheduled_at' => $startUtc,
            'duration_minutes' => $bookingType->duration_minutes,
            'address' => $this->addressFrom($data) ?: null,
            'currency' => config('shop.currency', 'USD'),
        ]);

        $workOrder->recordActivity('created', 'Booked online via '.$bookingType->name);

        return redirect()
            ->route('shop.book', $bookingType)
            ->with('booked', [
                'number' => $workOrder->number,
                'when' => $startUtc->setTimezone($bookingType->assignee->effectiveTimezone())->format('l, F j, Y \a\t g:i A'),
                'manage_url' => $this->manageUrl($workOrder),
            ]);
    }

    /** Signed scheduling page for an existing request. */
    public function schedule(Request $request, ServiceRequest $serviceRequest)
    {
        $types = BookingType::active()->whereNotNull('assigned_user_id')->orderBy('position')->get();
        abort_if($types->isEmpty(), 404);

        // Slots for every active type up front, so the visitor can switch type
        // without a page load (a query param would break the signed URL).
        [$from, $to] = $this->window();
        $slotsByType = [];
        foreach ($types as $t) {
            $slotsByType[$t->id] = $this->slots->availableSlots($t, $from, $to);
        }

        return view('shop.schedule', [
            'serviceRequest' => $serviceRequest,
            'types' => $types,
            'slotsByType' => $slotsByType,
        ]);
    }

    /** Schedule the request against the chosen type + slot. */
    public function scheduleStore(Request $request, ServiceRequest $serviceRequest)
    {
        $type = BookingType::active()->whereNotNull('assigned_user_id')
            ->where('slug', $request->input('type'))->first();
        abort_unless($type, 404);

        $data = $request->validate([
            'start' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $startUtc = CarbonImmutable::parse($data['start'])->utc();

        if (! $this->slots->isSlotOpen($type, $startUtc)) {
            return back()
                ->withErrors(['start' => 'Sorry, that time was just taken. Please pick another.'])
                ->withInput();
        }

        $workOrder = WorkOrder::create([
            'customer_id' => $serviceRequest->customer_id,
            'assigned_user_id' => $type->assigned_user_id,
            'booking_type_id' => $type->id,
            'service_request_id' => $serviceRequest->id,
            'title' => $serviceRequest->subject ?: $type->name,
            'notes' => trim(($serviceRequest->description ? $serviceRequest->description."\n\n" : '').($data['notes'] ?? '')) ?: null,
            'status' => 'scheduled',
            'scheduled_at' => $startUtc,
            'duration_minutes' => $type->duration_minutes,
            'address' => $serviceRequest->address ?: null,
            'currency' => config('shop.currency', 'USD'),
        ]);

        $serviceRequest->forceFill([
            'work_order_id' => $workOrder->id,
            'status' => 'converted',
        ])->save();

        $workOrder->recordActivity('created', 'Scheduled from request '.$serviceRequest->number);
        $serviceRequest->recordActivity('converted', 'Scheduled as work order '.$workOrder->number, ['work_order_id' => $workOrder->id]);

        return redirect()
            ->route('shop.schedule.done', $serviceRequest)
            ->with('booked', [
                'number' => $workOrder->number,
                'when' => $startUtc->setTimezone($type->assignee->effectiveTimezone())->format('l, F j, Y \a\t g:i A'),
            ]);
    }

    /** Simple confirmation landing for a scheduled request. */
    public function scheduleDone(ServiceRequest $serviceRequest)
    {
        abort_unless(session()->has('booked'), 404);

        return view('shop.schedule-done', [
            'serviceRequest' => $serviceRequest,
            'booked' => session('booked'),
        ]);
    }

    /* ---- Manage an existing booking (signed link) ------------------------ */

    /** Manage page: reschedule to a new slot, or cancel. */
    public function manage(WorkOrder $workOrder)
    {
        $type = $this->manageableType($workOrder);

        return view('shop.booking-manage', [
            'workOrder' => $workOrder,
            'type' => $type,
            // Exclude this booking from busy so its own slot stays offered.
            'days' => $type && $type->assignee
                ? $this->slots->availableSlots($type, ...[...$this->window(), $workOrder->id])
                : [],
        ]);
    }

    /** Move the booking to a new slot. */
    public function reschedule(Request $request, WorkOrder $workOrder)
    {
        $type = $this->manageableType($workOrder);

        $data = $request->validate(['start' => ['required', 'date']]);
        $startUtc = CarbonImmutable::parse($data['start'])->utc();

        if (! $type || ! $type->assignee || ! $this->slots->isSlotOpen($type, $startUtc, $workOrder->id)) {
            return back()->withErrors(['start' => 'Sorry, that time is no longer available. Please pick another.']);
        }

        $workOrder->update(['scheduled_at' => $startUtc, 'status' => 'scheduled']);
        $workOrder->recordActivity('rescheduled', 'Rescheduled online by customer');

        return redirect()->to($this->manageUrl($workOrder))->with('rescheduled', [
            'when' => $startUtc->setTimezone($type->assignee->effectiveTimezone())->format('l, F j, Y \a\t g:i A'),
        ]);
    }

    /** Cancel the booking (the calendar event is removed by the model hook). */
    public function cancel(Request $request, WorkOrder $workOrder)
    {
        $this->manageableType($workOrder);

        $workOrder->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => 'Cancelled online by customer',
        ]);
        $workOrder->recordActivity('cancelled', 'Cancelled online by customer');

        return view('shop.booking-cancelled', ['workOrder' => $workOrder]);
    }

    /** A booking is manageable only if it came from online booking and is live. */
    private function manageableType(WorkOrder $workOrder): ?BookingType
    {
        abort_unless($workOrder->booking_type_id && $workOrder->status !== 'cancelled', 404);

        return $workOrder->bookingType;
    }

    /** A signed, unguessable link to manage this booking. */
    private function manageUrl(WorkOrder $workOrder): string
    {
        return URL::signedRoute('shop.booking.manage', $workOrder);
    }

    /* ------------------------------------------------------------------ */

    /** @return array{0:CarbonImmutable, 1:CarbonImmutable} */
    private function window(): array
    {
        $from = CarbonImmutable::today();

        return [$from, $from->addDays(self::WINDOW_DAYS - 1)];
    }

    private function validateBooking(Request $request): array
    {
        return $request->validate([
            'start' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'line1' => ['nullable', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:64'],
            'postcode' => ['nullable', 'string', 'max:32'],
        ]);
    }

    /** Find or create the booking's customer by email. */
    private function customerFor(array $data): Customer
    {
        [$first, $last] = $this->splitName($data['name']);

        $customer = Customer::firstOrNew(['email' => $data['email']]);
        $customer->first_name = $customer->first_name ?: $first;
        $customer->last_name = $customer->last_name ?: $last;
        $customer->phone = $customer->phone ?: ($data['phone'] ?? null);
        $customer->save();

        return $customer;
    }

    /** @return array{0:string, 1:?string} */
    private function splitName(string $name): array
    {
        $name = trim($name);
        if (! Str::contains($name, ' ')) {
            return [$name, null];
        }

        return [Str::before($name, ' '), trim(Str::after($name, ' '))];
    }

    private function addressFrom(array $data): array
    {
        return array_filter([
            'line1' => $data['line1'] ?? null,
            'line2' => $data['line2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postcode' => $data['postcode'] ?? null,
        ], fn ($v) => filled($v));
    }
}
