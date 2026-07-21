<x-layouts.app title="Edit Discount">
    <x-page-header title="Edit Discount" icon="tag" :subtitle="$discount->code">
        <x-slot:actions>
            <x-badge :color="$discount->state_badge" dot>{{ \Illuminate\Support\Str::headline($discount->state) }}</x-badge>
            <x-button variant="secondary" href="{{ route('discounts.index') }}" icon="chevron-left">Back To Discounts</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.discounts._form')
</x-layouts.app>
