<x-layouts.app :title="'Edit ' . $bookingType->name">
    <x-page-header
        eyebrow="Booking Type"
        :title="'Edit ' . $bookingType->name"
        icon="clock"
        :subtitle="$bookingType->total_minutes . ' Minutes Total · ' . $bookingType->price_formatted"
        :back="['href' => route('booking-types.index'), 'label' => 'All Booking Types']" />

    @include('admin.booking-types._form')
</x-layouts.app>
