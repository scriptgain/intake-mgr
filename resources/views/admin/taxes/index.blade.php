<x-layouts.app title="Tax">
    <x-page-header
        eyebrow="Configuration"
        title="Tax"
        icon="percent"
        subtitle="Regional rules, applied by priority when more than one matches an address.">
        <x-slot:primary>
            <x-button href="{{ route('taxes.create') }}" icon="plus">New Tax Rule</x-button>
        </x-slot:primary>
    </x-page-header>

    <x-alert type="info" class="mb-6">
        Prices are calculated <strong>{{ \Illuminate\Support\Str::headline($taxMode) }}</strong> of tax.
        Change this in <a href="{{ route('settings.storefront.edit') }}" class="font-medium underline">Storefront Settings</a>.
    </x-alert>

    @if ($rules->isEmpty() && empty($filters['q']))
        <x-card>
            <x-empty-state icon="percent" title="No Tax Rules Yet"
                description="With no rules, no tax is added to any order. Add one rule for each region you are registered to collect in. When several rules match a shopper's address, the highest priority wins, so a state rule can override a country one."
                :steps="[
                    'Add a rule for the region you collect in, such as your home state.',
                    'Set the rate, and the tax class it applies to if you use more than the standard one.',
                    'Give it a priority if it needs to beat a broader rule that also matches.',
                ]">
                <x-slot:action>
                    <x-button icon="plus" href="{{ route('taxes.create') }}">Create Your First Tax Rule</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div x-data="{
                selected: [],
                allIds: [{{ $rules->pluck('id')->implode(',') }}],
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
            <form method="POST" action="{{ route('taxes.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            <x-data-surface>
                <x-slot:search>
                    <form method="GET" action="{{ route('taxes.index') }}" class="flex flex-wrap items-center gap-2">
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <label for="tax-search" class="sr-only">Search Tax Rules</label>
                            <input id="tax-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                                placeholder="Rule Name"
                                class="block w-full min-w-0 rounded-lg border-0 bg-white py-1.5 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:w-64">
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Search</x-button>
                        @if (! empty($filters['q']))
                            <x-button variant="ghost" size="sm" href="{{ route('taxes.index') }}">Clear</x-button>
                        @endif
                    </form>
                </x-slot:search>

                <x-slot:bulk>
                    <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5">
                        <span class="text-sm font-medium text-brand-900"><span x-text="selected.length"></span> Selected</span>
                        <div class="flex items-center gap-2">
                            <x-button type="button" variant="ghost" size="sm" x-on:click="selected = []">Clear Selection</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash"
                                x-on:click="$dispatch('open-modal', 'bulk-delete-taxes')">Delete Selected</x-button>
                        </div>
                    </div>
                </x-slot:bulk>

                @if ($rules->isEmpty())
                    <x-empty-state icon="search" title="No Rules Match That Search"
                        description="Try a shorter search term, or clear it to see every rule.">
                        <x-slot:action>
                            <x-button href="{{ route('taxes.index') }}" variant="secondary" size="sm">Show All Tax Rules</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th class="vx-col-select"><span class="sr-only">Select</span>@include('admin._select-all-toggle')</th>
                                <th>Name</th>
                                <th>Region</th>
                                <th class="text-right">Rate</th>
                                <th>Tax Class</th>
                                <th>Status</th>
                                <th class="vx-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rules as $rule)
                                <tr>
                                    <td class="vx-col-select">@include('admin._select-toggle', ['id' => $rule->id])</td>
                                    <td><a href="{{ route('taxes.edit', $rule) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $rule->name }}</a></td>
                                    <td class="text-slate-600">{{ $rule->region_label }}</td>
                                    <td class="tabular text-right font-medium text-slate-900">{{ $rule->rate_label }}</td>
                                    <td class="text-slate-600">{{ $rule->tax_class }}</td>
                                    <td><x-badge :color="$rule->is_active ? 'success' : 'neutral'" dot>{{ $rule->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                    <td class="vx-col-actions">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-button href="{{ route('taxes.edit', $rule) }}" icon="edit" title="Edit Tax Rule" />
                                            <x-delete-button :action="route('taxes.destroy', $rule)" name="del-tax-{{ $rule->id }}"
                                                label="Delete Tax Rule"
                                                title="Delete This Tax Rule?"
                                                :message="'New orders matching ' . $rule->region_label . ' will no longer have this tax added. Past orders keep the tax they were charged.'" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-data-surface>

            <x-modal name="bulk-delete-taxes" title="Delete Selected Tax Rules?" icon="warning" tone="danger" maxWidth="max-w-md">
                New orders matching these regions will no longer have this tax added. Past orders keep the tax they were charged. This cannot be undone.
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'bulk-delete-taxes')">Cancel</x-button>
                    <x-button variant="danger" size="sm" icon="trash"
                        x-on:click="submitBulk(); $dispatch('close-modal', 'bulk-delete-taxes')">Delete Tax Rules</x-button>
                </x-slot:footer>
            </x-modal>
        </div>

        <div class="mt-6">{{ $rules->links() }}</div>
    @endif
</x-layouts.app>
