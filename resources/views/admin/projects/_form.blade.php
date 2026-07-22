<form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="space-y-6">
    @csrf
    @if ($project->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Details" subtitle="What the engagement is and when it runs.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-field label="Name" for="name" required :error="$errors->first('name')" class="sm:col-span-2">
                        <x-input id="name" name="name" :value="old('name', $project->name)" required autofocus />
                    </x-field>
                    <x-field label="Status" for="status" required :error="$errors->first('status')">
                        <x-select id="status" name="status">
                            @foreach (\App\Models\Project::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $project->status) === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Assigned Agent" for="assigned_user_id" :error="$errors->first('assigned_user_id')">
                        <x-select id="assigned_user_id" name="assigned_user_id">
                            <option value="">Unassigned</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected((int) old('assigned_user_id', $project->assigned_user_id) === $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Starts On" for="starts_on" :error="$errors->first('starts_on')">
                        <x-input id="starts_on" name="starts_on" type="date" :value="old('starts_on', $project->starts_on?->format('Y-m-d'))" />
                    </x-field>
                    <x-field label="Due On" for="due_on" :error="$errors->first('due_on')">
                        <x-input id="due_on" name="due_on" type="date" :value="old('due_on', $project->due_on?->format('Y-m-d'))" />
                    </x-field>
                    <x-field label="Description" for="description" :error="$errors->first('description')" class="sm:col-span-2">
                        <textarea id="description" name="description" rows="5"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('description', $project->description) }}</textarea>
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Customer">
                <x-field label="Customer" for="customer_id" :error="$errors->first('customer_id')">
                    <x-select id="customer_id" name="customer_id">
                        <option value="">No Customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_id', $project->customer_id) === $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>
            </x-card>
        </div>
    </div>

    <div class="sticky bottom-0 z-20 -mx-4 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(15,23,42,0.15)] sm:-mx-6 sm:px-6">
        <x-button variant="secondary" href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}">Cancel</x-button>
        <x-button type="submit" icon="check">{{ $project->exists ? 'Save Changes' : 'Create Project' }}</x-button>
    </div>
</form>
