<x-layouts.app title="New Booking Type">
    <x-page-header
        eyebrow="Scheduling"
        title="New Booking Type"
        icon="clock"
        subtitle="Define a kind of appointment with its duration, buffers, and price."
        :back="['href' => route('booking-types.index'), 'label' => 'All Booking Types']" />

    @include('admin.booking-types._form')
</x-layouts.app>
