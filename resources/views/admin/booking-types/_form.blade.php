<form method="POST" action="{{ $bookingType->exists ? route('booking-types.update', $bookingType) : route('booking-types.store') }}" class="space-y-6">
    @csrf
    @if ($bookingType->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Details" subtitle="What this kind of appointment is called and how long it runs.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Name" for="name" required :error="$errors->first('name')" class="sm:col-span-2">
                        <x-input id="name" name="name" :value="old('name', $bookingType->name)" required autofocus placeholder="Standard Service Call" />
                    </x-field>
                    <x-field label="Description" for="description" hint="Optional. Shown when a booking type is chosen." :error="$errors->first('description')" class="sm:col-span-2">
                        <textarea id="description" name="description" rows="4"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('description', $bookingType->description) }}</textarea>
                    </x-field>
                </div>
            </x-card>

            <x-card title="Timing" subtitle="Duration plus any buffer padding before and after each appointment.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <x-field label="Duration (Minutes)" for="duration_minutes" required :error="$errors->first('duration_minutes')">
                        <x-input id="duration_minutes" name="duration_minutes" type="number" min="5" step="5" :value="old('duration_minutes', $bookingType->duration_minutes)" required />
                    </x-field>
                    <x-field label="Buffer Before (Minutes)" for="buffer_before_minutes" required :error="$errors->first('buffer_before_minutes')">
                        <x-input id="buffer_before_minutes" name="buffer_before_minutes" type="number" min="0" :value="old('buffer_before_minutes', $bookingType->buffer_before_minutes)" required />
                    </x-field>
                    <x-field label="Buffer After (Minutes)" for="buffer_after_minutes" required :error="$errors->first('buffer_after_minutes')">
                        <x-input id="buffer_after_minutes" name="buffer_after_minutes" type="number" min="0" :value="old('buffer_after_minutes', $bookingType->buffer_after_minutes)" required />
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Assignment & Price">
                <div class="space-y-5">
                    <x-field label="Default Technician" for="assigned_user_id" hint="Leave unassigned to allow any available technician." :error="$errors->first('assigned_user_id')">
                        <x-select id="assigned_user_id" name="assigned_user_id">
                            <option value="">Any Available</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected((int) old('assigned_user_id', $bookingType->assigned_user_id) === $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Price" for="price" hint="Leave blank for a free appointment." :error="$errors->first('price')">
                        <x-input id="price" name="price" inputmode="decimal"
                            :value="old('price', $bookingType->price_cents ? number_format($bookingType->price_cents / 100, 2, '.', '') : '')"
                            placeholder="0.00" />
                    </x-field>
                    <x-field label="Color" hint="Optional hex used on the calendar, e.g. #2563eb." :error="$errors->first('color')">
                        <div class="flex items-center gap-2" x-data="{ color: '{{ old('color', $bookingType->color ?: '') }}' }">
                            <input type="color" aria-label="Pick Color"
                                :value="color || '#2563eb'" x-on:input="color = $event.target.value"
                                class="h-9 w-12 shrink-0 cursor-pointer rounded-lg border-0 bg-white p-1 ring-1 ring-inset ring-slate-200">
                            <x-input id="color" name="color" x-model="color" placeholder="#2563eb" class="tabular font-mono" />
                        </div>
                    </x-field>
                </div>
            </x-card>

            <x-card title="Visibility">
                <div class="space-y-5">
                    <x-toggle name="is_active" :checked="old('is_active', $bookingType->is_active ?? true)"
                        label="Active" description="Only active booking types can be scheduled." />
                    <x-field label="Position" for="position" hint="Lower numbers sort first." :error="$errors->first('position')">
                        <x-input id="position" name="position" type="number" min="0" :value="old('position', $bookingType->position ?? 0)" />
                    </x-field>
                </div>
            </x-card>
        </div>
    </div>

    <div class="sticky bottom-0 z-20 -mx-4 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,0.15)] sm:-mx-6 sm:px-6">
        <x-button variant="secondary" href="{{ route('booking-types.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $bookingType->exists ? 'Save Changes' : 'Create Booking Type' }}</x-button>
    </div>
</form>
