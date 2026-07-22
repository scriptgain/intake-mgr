<x-layouts.app title="Availability">
    <x-page-header
        eyebrow="Scheduling"
        title="Availability"
        icon="clock"
        subtitle="Each staff member's recurring weekly working hours and their days off." />

    @if ($staff->isEmpty())
        <x-card>
            <x-empty-state icon="users" title="No Staff Yet"
                description="Availability is set per staff member. Once you have added staff, each person will appear here so you can set their working hours and time off." />
        </x-card>
    @else
        <x-data-surface>
            <x-table flush>
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th class="text-right">Active Days</th>
                        <th>Timezone</th>
                        <th class="vx-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staff as $member)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold uppercase text-slate-500 ring-1 ring-slate-200">
                                        {{ \Illuminate\Support\Str::of($member->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('availability.edit', $member) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $member->name }}</a>
                                        <span class="block truncate text-xs text-slate-500">{{ $member->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="tabular text-right">
                                @if ($member->active_weekday_count > 0)
                                    <x-badge color="success" dot>{{ $member->active_weekday_count }} / 7 Days</x-badge>
                                @else
                                    <x-badge color="neutral" dot>No Hours Set</x-badge>
                                @endif
                            </td>
                            <td class="text-slate-600">{{ $member->effectiveTimezone() }}</td>
                            <td class="vx-col-actions">
                                <div class="flex items-center justify-end gap-1">
                                    <x-icon-button href="{{ route('availability.edit', $member) }}" icon="edit" title="Edit Availability" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-data-surface>
    @endif
</x-layouts.app>
