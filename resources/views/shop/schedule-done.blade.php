<x-layouts.shop title="Appointment Scheduled">
    <div class="mx-auto max-w-xl px-4 py-14">
        <x-card class="text-center">
            <span class="mx-auto mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-200">
                <x-icon name="check-circle" class="h-7 w-7" />
            </span>
            <h1 class="text-2xl font-semibold text-shop-ink">You're Scheduled!</h1>
            <p class="mt-2 text-shop-muted">Request {{ $serviceRequest->number }} is booked for</p>
            <p class="mt-1 text-lg font-semibold text-shop-ink">{{ $booked['when'] }}</p>
            <p class="mt-3 text-sm text-shop-muted">Work order <span class="font-medium text-shop-ink">{{ $booked['number'] }}</span></p>
            <div class="mt-6 flex justify-center">
                <x-button href="{{ route('shop.home') }}">Back To Home</x-button>
            </div>
        </x-card>
    </div>
</x-layouts.shop>
