<x-layouts.app title="Discounts">
    <x-page-header
        eyebrow="Sales"
        title="Discounts"
        icon="tag"
        subtitle="Codes shoppers type at checkout, and how far each one has been used.">
        <x-slot:primary>
            <x-button href="{{ route('discounts.create') }}" icon="plus">New Discount</x-button>
        </x-slot:primary>
    </x-page-header>

    @if ($discounts->isEmpty() && empty($filters['q']))
        <x-card>
            <x-empty-state icon="tag" title="No Discount Codes Yet"
                description="A discount takes a percentage off, a fixed amount off, or makes shipping free. You can cap how many times it is used in total, and set the window it is valid for, so a launch promotion expires on its own."
                :steps="[
                    'Choose the code shoppers will type, such as WELCOME10.',
                    'Pick the type and value, and whether it applies to everything or one collection.',
                    'Optionally set a usage limit and an end date, then activate it.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('discounts.create') }}">Create Your First Discount</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                allIds: [{{ $discounts->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('discounts.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:search>
                    <form method="GET" action="{{ route('discounts.index') }}" class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="discount-search" class="sr-only">Search Discount Codes</label>
                            <input id="discount-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Discount Code"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-64">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('discounts.index') }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-discounts')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($discounts->isEmpty())
                    <x-empty-state icon="search" title="No Codes Match That Search"
                        description="Try a shorter search term, or clear it to see every code.">
                        <x-slot:action>
                            <x-button href="{{ route('discounts.index') }}" variant="secondary" size="sm">Show All Discounts</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Code</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th class="text-right">Used</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($discounts as $discount)
                                <tr class="vx-rail vx-rail-{{ $discount->state_badge === 'success' ? 'none' : $discount->state_badge }}">
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $discount->id])</td>
                                    <td>
                                        <a href="{{ route('discounts.edit', $discount) }}" class="font-mono font-medium text-slate-900 hover:text-brand-700">{{ $discount->code }}</a>
                                        @if ($discount->title)<span class="block text-xs text-slate-500">{{ $discount->title }}</span>@endif
                                    </td>
                                    <td class="text-slate-600">{{ $discount->value_label }}</td>
                                    <td><x-badge :color="$discount->state_badge" dot>{{ \Illuminate\Support\Str::headline($discount->state) }}</x-badge></td>
                                    <td class="tabular text-right">
                                        {{ $discount->redemptions_count }}@if ($discount->usage_limit)<span class="text-slate-400"> / {{ $discount->usage_limit }}</span>@endif
                                    </td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-button href="{{ route('discounts.edit', $discount) }}" icon="edit" title="Edit Discount" />
                                            <x-delete-button :action="route('discounts.destroy', $discount)" name="del-discount-{{ $discount->id }}"
                                                label="Delete Discount"
                                                title="Delete This Discount?"
                                                :message="'The code ' . $discount->code . ' will stop working at checkout immediately. Orders that already used it keep their discount and their history.'" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-discounts" title="Delete Selected Discounts?" icon="warning" tone="danger" maxWidth="max-w-md">
                These codes will stop working at checkout immediately. Orders that already used them keep their discount and their history. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-discounts')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-discounts')">Delete Discounts</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $discounts->links() }}</div>
    @endif
</x-layouts.app>
