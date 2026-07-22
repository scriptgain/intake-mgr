<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityRule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Per-staff working hours. Not a resource: staff already exist, so this only
 * lists them and edits each member's recurring weekly availability plus their
 * date-specific exceptions (days off or special hours).
 */
class AvailabilityController extends Controller
{
    public function index()
    {
        $staff = User::withCount(['availabilityRules as active_weekday_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('admin.availability.index', [
            'staff' => $staff,
        ]);
    }

    public function edit(User $user)
    {
        $user->load(['availabilityRules', 'availabilityExceptions']);

        // One editable block per weekday (0=Sun..6=Sat). The model supports many
        // blocks per day, but the UI edits the first active one for simplicity.
        $days = [];
        foreach (AvailabilityRule::WEEKDAYS as $weekday => $label) {
            $rule = $user->availabilityRules
                ->where('weekday', $weekday)
                ->firstWhere('is_active', true)
                ?? $user->availabilityRules->firstWhere('weekday', $weekday);

            $days[$weekday] = [
                'label' => $label,
                'enabled' => (bool) $rule,
                'start' => $rule ? substr((string) $rule->start_time, 0, 5) : '09:00',
                'end' => $rule ? substr((string) $rule->end_time, 0, 5) : '17:00',
            ];
        }

        $exceptions = $user->availabilityExceptions->map(fn ($ex) => [
            'date' => $ex->date?->format('Y-m-d'),
            'is_available' => (bool) $ex->is_available,
            'start' => $ex->start_time ? substr((string) $ex->start_time, 0, 5) : '',
            'end' => $ex->end_time ? substr((string) $ex->end_time, 0, 5) : '',
            'reason' => $ex->reason ?? '',
        ])->values()->all();

        return view('admin.availability.edit', [
            'user' => $user,
            'days' => $days,
            'exceptions' => $exceptions,
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'days' => ['array'],
            'days.*.enabled' => ['nullable', 'boolean'],
            'days.*.start' => ['nullable', 'date_format:H:i'],
            'days.*.end' => ['nullable', 'date_format:H:i'],
            'exceptions' => ['array'],
            'exceptions.*.date' => ['required', 'date'],
            'exceptions.*.is_available' => ['nullable', 'boolean'],
            'exceptions.*.start' => ['nullable', 'date_format:H:i'],
            'exceptions.*.end' => ['nullable', 'date_format:H:i'],
            'exceptions.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        // A day that is switched on needs both times, with start before end.
        $validator = validator([], []);
        foreach (($data['days'] ?? []) as $weekday => $day) {
            if (empty($day['enabled'])) {
                continue;
            }
            $start = $day['start'] ?? null;
            $end = $day['end'] ?? null;
            if (blank($start) || blank($end)) {
                $validator->errors()->add("days.{$weekday}.start", 'A start and end time are required for '.(AvailabilityRule::WEEKDAYS[$weekday] ?? 'this day').'.');
            } elseif ($start >= $end) {
                $validator->errors()->add("days.{$weekday}.end", 'The end time must be after the start time for '.(AvailabilityRule::WEEKDAYS[$weekday] ?? 'this day').'.');
            }
        }
        if ($validator->errors()->isNotEmpty()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($user, $data) {
            $user->forceFill(['timezone' => $data['timezone']])->save();

            // Replace the recurring rules: one active rule per enabled weekday.
            $user->availabilityRules()->delete();
            foreach (($data['days'] ?? []) as $weekday => $day) {
                if (empty($day['enabled'])) {
                    continue;
                }
                $user->availabilityRules()->create([
                    'weekday' => (int) $weekday,
                    'start_time' => $day['start'],
                    'end_time' => $day['end'],
                    'is_active' => true,
                ]);
            }

            // Replace the exceptions from the submitted list.
            $user->availabilityExceptions()->delete();
            foreach (($data['exceptions'] ?? []) as $exception) {
                if (blank($exception['date'] ?? null)) {
                    continue;
                }
                $available = (bool) ($exception['is_available'] ?? false);
                $user->availabilityExceptions()->create([
                    'date' => $exception['date'],
                    'is_available' => $available,
                    'start_time' => $available ? ($exception['start'] ?: null) : null,
                    'end_time' => $available ? ($exception['end'] ?: null) : null,
                    'reason' => $exception['reason'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('availability.edit', $user)
            ->with('status', 'Availability saved.');
    }
}
