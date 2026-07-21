<x-layouts.app title="New Discount">
    <x-page-header title="New Discount" icon="tag" subtitle="Create a code shoppers can apply at checkout.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('discounts.index') }}" icon="chevron-left">Back To Discounts</x-button>
        </x-slot:actions>
    </x-page-header>

    @include('admin.discounts._form')
</x-layouts.app>
