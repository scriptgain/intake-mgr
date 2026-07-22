<x-layouts.shop title="My Tickets">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">My Account</h1>
        <p class="mt-2 text-shop-muted">Your support conversations with our team.</p>
    </section>

    <x-account-tabs />

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <h2 class="text-lg font-semibold text-shop-ink mb-6">Support Tickets</h2>

        @if ($tickets->isEmpty())
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white">
                <x-empty-state icon="envelope" title="No Tickets Yet" description="When a request turns into a support conversation, it will appear here so you can reply and follow along.">
                    <x-slot:action>
                        <x-button href="{{ route('shop.request') }}" icon="plus">Request Service</x-button>
                    </x-slot:action>
                </x-empty-state>
            </div>
        @else
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white p-1.5">
                @foreach ($tickets as $ticket)
                    <a href="{{ route('shop.account.ticket', $ticket) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-lg px-4 py-4 transition hover:bg-slate-50 hover:ring-1 hover:ring-inset hover:ring-slate-200">
                        <div class="min-w-0">
                            <p class="font-medium text-shop-ink truncate">{{ $ticket->subject }}</p>
                            <p class="text-sm text-shop-muted">{{ $ticket->number }} &middot; {{ ($ticket->last_reply_at ?? $ticket->created_at)->format('F j, Y') }}</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <x-badge :color="$ticket->priority_badge" dot>{{ $ticket->priority_label }}</x-badge>
                            <x-badge :color="$ticket->status_badge" dot>{{ $ticket->status_label }}</x-badge>
                            <x-icon name="chevron-right" class="w-4 h-4 text-shop-muted" />
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">{{ $tickets->links() }}</div>
        @endif
    </section>

</x-layouts.shop>
