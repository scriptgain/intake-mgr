<x-layouts.app :title="'Edit ' . $quote->number">
    <x-page-header
        eyebrow="Service Desk"
        :title="'Edit ' . $quote->number"
        icon="document"
        :subtitle="$quote->title"
        :back="['href' => route('quotes.show', $quote), 'label' => 'Back To Quote']" />

    @include('admin.quotes._form')
</x-layouts.app>
