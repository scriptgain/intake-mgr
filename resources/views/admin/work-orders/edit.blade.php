<x-layouts.app :title="'Edit ' . $workOrder->number">
    <x-page-header
        eyebrow="Work Order"
        :title="'Edit ' . $workOrder->number"
        icon="truck"
        :subtitle="$workOrder->title"
        :back="['href' => route('work-orders.show', $workOrder), 'label' => 'Back To Work Order']" />

    @include('admin.work-orders._form')
</x-layouts.app>
