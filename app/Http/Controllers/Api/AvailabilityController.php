<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AvailabilityResource;
use App\Models\AvailabilityRule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Per-staff working hours over the API. Mirrors Admin\AvailabilityController:
 * a staff member's timezone, recurring weekly rules and date-specific
 * exceptions are read and replaced wholesale.
 */
class AvailabilityController extends Controller
{
    public function show(User $user)
    {
        return new AvailabilityResource(
            $user->load(['availabilityRules', 'availabilityExceptions'])
        );
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
        $validator = Validator::make([], []);
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
            throw new ValidationException($validator);
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

        return new AvailabilityResource(
            $user->load(['availabilityRules', 'availabilityExceptions'])
        );
    }
}
