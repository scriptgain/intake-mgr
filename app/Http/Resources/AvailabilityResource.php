<?php

namespace App\Http\Resources;

use App\Models\AvailabilityRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff member's schedule: their timezone, recurring weekly rules and
 * date-specific exceptions. Wraps a User with availabilityRules +
 * availabilityExceptions loaded.
 *
 * @mixin \App\Models\User
 */
class AvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->id,
            'timezone' => $this->effectiveTimezone(),
            'rules' => $this->availabilityRules->map(fn (AvailabilityRule $rule) => [
                'weekday' => (int) $rule->weekday,
                'weekday_label' => $rule->weekday_label,
                'start_time' => $rule->start_time ? substr((string) $rule->start_time, 0, 5) : null,
                'end_time' => $rule->end_time ? substr((string) $rule->end_time, 0, 5) : null,
                'is_active' => (bool) $rule->is_active,
            ])->values(),
            'exceptions' => $this->availabilityExceptions->map(fn ($ex) => [
                'date' => $ex->date?->format('Y-m-d'),
                'is_available' => (bool) $ex->is_available,
                'start_time' => $ex->start_time ? substr((string) $ex->start_time, 0, 5) : null,
                'end_time' => $ex->end_time ? substr((string) $ex->end_time, 0, 5) : null,
                'reason' => $ex->reason,
            ])->values(),
        ];
    }
}
