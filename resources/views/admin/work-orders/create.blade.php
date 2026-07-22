<x-layouts.app title="New Work Order">
    <x-page-header
        eyebrow="Service Desk"
        title="New Work Order"
        icon="truck"
        subtitle="Schedule a job, assign it, and add the line items for the services performed."
        :back="['href' => route('work-orders.index'), 'label' => 'All Work Orders']" />

    @include('admin.work-orders._form')
</x-layouts.app>
