<x-layouts.app title="Shipping">
    <x-page-header
        eyebrow="Configuration"
        title="Shipping"
        icon="truck"
        subtitle="The regions you deliver to, and what you charge inside each one.">
        <x-slot:primary>
            <x-button href="{{ route('shipping.create') }}" icon="plus">New Zone</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($zones->isEmpty())
        <x-card>
            <x-empty-state icon="truck" title="No Shipping Zones Yet"
                description="Checkout cannot quote a delivery price until at least one zone covers the shopper's country, so orders will fail without this. A zone is a group of countries; the rates inside it are the options the shopper picks from."
                :steps="[
                    'Create a zone and choose the countries it covers.',
                    'Add at least one rate to it, such as Standard at a flat price.',
                    'Repeat for any region that needs different pricing.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('shipping.create') }}">Create Your First Zone</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                allIds: [{{ $zones->pluck('id')->implode(',') }}],
                submitBulk() {
                    const form = this.$refs.bulkForm;
                    form.querySelectorAll('input.js-dyn').forEach(node => node.remove());
                    this.selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden'; input.name = 'ids[]'; input.value = id; input.className = 'js-dyn';
                        form.appendChild(input);
                    });
                    form.submit();
                }
            }">
            <form method="POST" action="{{ route('shipping.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-zones')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                <x-table flush>
                    <thead>
                        <tr>
                            <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                            <th>Zone</th>
                            <th>Coverage</th>
                            <th class="text-right">Rates</th>
                            <th>Status</th>
                            <th class="vx-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($zones as $zone)
                            {{-- An active zone with no rates quotes nothing, so checkout
                                 fails for everyone in it. That is worth flagging. --}}
                            <tr class="vx-rail {{ $zone->is_active && $zone->rates->isEmpty() ? 'vx-rail-danger' : 'vx-rail-none' }}">
                                <td class="vx-col-select">@include('admin._select-toggle', ['id' => $zone->id])</td>
                                <td>
                                    <a href="{{ route('shipping.edit', $zone) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $zone->name }}</a>
                                    @if ($zone->is_active && $zone->rates->isEmpty())
                                        <span class="block text-xs text-rose-600">No rates, so checkout cannot quote</span>
                                    @endif
                                </td>
                                <td class="text-slate-600">{{ $zone->country_label }}</td>
                                <td class="tabular text-right {{ $zone->rates->isEmpty() ? 'text-slate-400' : 'text-slate-700' }}">{{ $zone->rates->count() }}</td>
                                <td><x-badge :color="$zone->is_active ? 'success' : 'neutral'" dot>{{ $zone->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                <td class="vx-col-actions">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-icon-button href="{{ route('shipping.edit', $zone) }}" icon="edit" title="Edit Zone" />
                                        <x-delete-button :action="route('shipping.destroy', $zone)" name="del-zone-{{ $zone->id }}"
                                            label="Delete Zone"
                                            title="Delete This Shipping Zone?"
                                            :message="'This removes \'' . $zone->name . '\' and every rate inside it. Shoppers in those countries will no longer be quoted a delivery price, and checkout will fail for them unless another zone covers them.'" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            </x-data-surface>

            <x-modal name="bulk-delete-zones" title="Delete Selected Zones?" icon="warning" tone="danger" maxWidth="max-w-md">
                This removes the selected zones and every rate inside them. Shoppers in those countries will no longer be quoted a delivery price, and checkout will fail for them unless another zone covers them. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-zones')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-zones')">Delete Zones</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @endif
</x-layouts.app>
