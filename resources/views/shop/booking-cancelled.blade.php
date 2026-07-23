<x-layouts.shop title="Booking Cancelled">
    <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="mx-auto max-w-xl rounded-3xl bg-white p-8 text-center ring-1 ring-shop-line shadow-sm">
            <span class="mx-auto mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-rose-50 text-rose-600 ring-1 ring-inset ring-rose-200">
                <x-icon name="x-circle" class="h-7 w-7" />
            </span>
            <h1 class="text-2xl font-bold tracking-tight text-shop-ink">Booking Cancelled</h1>
            <p class="mt-2 text-shop-muted">Your appointment {{ $workOrder->number }} has been cancelled and removed from our calendar. No further action is needed.</p>
            <div class="mt-6 flex justify-center gap-3">
                <x-button href="{{ route('shop.home') }}" variant="secondary">Back To Home</x-button>
                @if ($workOrder->bookingType)
                    <x-button href="{{ route('shop.book', $workOrder->bookingType) }}">Book Again</x-button>
                @endif
            </div>
        </div>
    </div>
</x-layouts.shop>
