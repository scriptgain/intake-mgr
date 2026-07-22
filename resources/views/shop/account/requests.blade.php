<x-layouts.shop title="My Requests">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-shop-ink">My Account</h1>
        <p class="mt-2 text-shop-muted">Track the service requests you have submitted.</p>
    </section>

    <x-account-tabs />

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <div class="flex items-center justify-between gap-4 mb-6">
            <h2 class="text-lg font-semibold text-shop-ink">Service Requests</h2>
            <x-button href="{{ route('shop.request') }}" icon="plus" size="sm">New Request</x-button>
        </div>

        @if ($requests->isEmpty())
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white">
                <x-empty-state icon="folder" title="No Requests Yet" description="When you ask us for service, your request will show up here so you can follow its progress.">
                    <x-slot:action>
                        <x-button href="{{ route('shop.request') }}" icon="plus">Request Service</x-button>
                    </x-slot:action>
                </x-empty-state>
            </div>
        @else
            <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white p-1.5">
                @foreach ($requests as $req)
                    <a href="{{ route('shop.account.request', $req) }}" class="flex flex-wrap items-center justify-between gap-4 rounded-lg px-4 py-4 transition hover:bg-slate-50 hover:ring-1 hover:ring-inset hover:ring-slate-200">
                        <div class="min-w-0">
                            <p class="font-medium text-shop-ink truncate">{{ $req->subject }}</p>
                            <p class="text-sm text-shop-muted">{{ $req->number }} &middot; {{ $req->created_at->format('F j, Y') }}</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <x-badge :color="$req->status_badge" dot>{{ $req->status_label }}</x-badge>
                            <x-icon name="chevron-right" class="w-4 h-4 text-shop-muted" />
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">{{ $requests->links() }}</div>
        @endif
    </section>

</x-layouts.shop>
