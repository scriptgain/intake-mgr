<x-layouts.app title="New Quote">
    <x-page-header
        eyebrow="Service Desk"
        title="New Quote"
        icon="document"
        subtitle="Propose the services and prices, then send it to the customer to accept."
        :back="['href' => route('quotes.index'), 'label' => 'All Quotes']" />

    @include('admin.quotes._form')
</x-layouts.app>
