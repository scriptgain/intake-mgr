<x-layouts.shop :title="'Work Order ' . $workOrder->number">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <a href="{{ route('shop.account.work-orders') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-shop-muted hover:text-shop-ink transition">
            <x-icon name="chevron-left" class="w-4 h-4" /> Back To Work Orders
        </a>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-3xl font-semibold tracking-tight text-shop-ink">{{ $workOrder->title }}</h1>
                <p class="mt-1 text-shop-muted">{{ $workOrder->number }}</p>
            </div>
            <x-badge :color="$workOrder->status_badge" dot>{{ $workOrder->status_label }}</x-badge>
        </div>
    </section>

    <div class="section-divider"></div>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

            <div class="min-w-0 lg:col-span-2 space-y-8">
                {{-- Schedule --}}
                <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200">
                                <x-icon name="clock" class="w-5 h-5" />
                            </span>
                            <div>
                                <p class="text-xs font-medium text-shop-muted">Scheduled</p>
                                <p class="text-sm font-semibold text-shop-ink">
                                    {{ $workOrder->scheduled_at ? $workOrder->scheduled_at->format('F j, Y g:i A') : 'To Be Scheduled' }}
                                </p>
                                @if ($workOrder->assignee)
                                    <p class="mt-0.5 text-xs text-shop-muted">Technician: {{ $workOrder->assignee->name }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-add-to-calendar :work-order="$workOrder" :ics-url="route('account.work-order.ics', $workOrder)" />
                            @if ($workOrder->is_changeable)
                                <x-button variant="secondary" size="sm" icon="clock" x-data @click="$dispatch('open-modal', 'reschedule-wo')">Reschedule</x-button>
                                <x-button variant="secondary" size="sm" icon="x-circle" x-data @click="$dispatch('open-modal', 'cancel-wo')">Cancel</x-button>
                            @endif
                        </div>
                    </div>

                    @if ($workOrder->notes)
                        <div class="mt-4 pt-4 border-t border-shop-line">
                            <p class="text-sm text-shop-ink whitespace-pre-line leading-relaxed">{{ $workOrder->notes }}</p>
                        </div>
                    @endif
                </div>

                {{-- Line items --}}
                @if ($workOrder->items->isNotEmpty())
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-4">Services</h2>
                        <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white divide-y divide-shop-line">
                            @foreach ($workOrder->items as $item)
                                <div class="flex items-center justify-between gap-4 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-shop-ink">{{ $item->name }}</p>
                                        @if ($item->description)
                                            <p class="text-xs text-shop-muted">{{ $item->description }}</p>
                                        @endif
                                        <p class="text-xs text-shop-muted">Qty {{ $item->quantity }} &middot; {{ $item->unit_price_formatted }}</p>
                                    </div>
                                    <p class="text-sm font-medium text-shop-ink tabular shrink-0">{{ $item->total_formatted }}</p>
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between gap-4 px-4 py-3 bg-slate-50/60">
                                <p class="text-sm font-semibold text-shop-ink">Subtotal</p>
                                <p class="text-sm font-semibold text-shop-ink tabular">{{ $workOrder->subtotal_formatted }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($workOrder->address)
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-2">Service Address</h2>
                        <address class="not-italic text-sm text-shop-muted leading-relaxed">
                            {{ $workOrder->address['line1'] ?? '' }}<br>
                            @if (! empty($workOrder->address['line2'])){{ $workOrder->address['line2'] }}<br>@endif
                            {{ $workOrder->address['city'] ?? '' }}@if (! empty($workOrder->address['state'])), {{ $workOrder->address['state'] }}@endif {{ $workOrder->address['postcode'] ?? '' }}
                        </address>
                    </div>
                @endif
            </div>

            {{-- Summary rail --}}
            <div class="space-y-6">
                @if ($workOrder->invoice)
                    <x-card title="Invoice">
                        <div class="flex items-center justify-between gap-3">
                            <a href="{{ route('shop.account.order', $workOrder->invoice) }}" class="text-sm font-medium text-brand-700 hover:text-brand-800">{{ $workOrder->invoice->number }}</a>
                            <x-badge :color="$workOrder->invoice->financial_badge" dot>{{ \Illuminate\Support\Str::headline($workOrder->invoice->financial_status) }}</x-badge>
                        </div>
                        <p class="mt-2 text-2xl font-semibold text-shop-ink tabular">{{ $workOrder->invoice->total_formatted }}</p>
                        @if ($payUrl)
                            <x-button href="{{ $payUrl }}" class="mt-4 w-full" icon="credit-card">Pay Now</x-button>
                        @endif
                    </x-card>
                @endif

                @if ($workOrder->ticket)
                    <x-card title="Linked Ticket">
                        <a href="{{ route('shop.account.ticket', $workOrder->ticket) }}" class="flex items-center justify-between gap-3 text-sm font-medium text-shop-ink">
                            <span class="inline-flex items-center gap-2"><x-icon name="envelope" class="w-4 h-4 text-shop-muted" /> {{ $workOrder->ticket->number }}</span>
                            <x-icon name="chevron-right" class="w-4 h-4 text-shop-muted" />
                        </a>
                    </x-card>
                @endif

                <x-card title="Activity">
                    <x-account-timeline :activities="$workOrder->activities" />
                </x-card>
            </div>
        </div>
    </section>

    {{-- Reschedule + Cancel modals (only meaningful while changeable) --}}
    @if ($workOrder->is_changeable)
        <x-modal name="reschedule-wo" title="Request A New Time" icon="clock" subtitle="Tell us when works better and we will confirm it.">
            <form method="POST" action="{{ route('shop.account.work-order.reschedule', $workOrder) }}" id="reschedule-wo-form">
                @csrf
                <x-field label="Preferred Date &amp; Time" for="preferred_at" required :error="$errors->first('preferred_at')">
                    <x-input type="datetime-local" id="preferred_at" name="preferred_at" required />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'reschedule-wo')">Cancel</x-button>
                <x-button type="submit" form="reschedule-wo-form">Send Request</x-button>
            </x-slot:footer>
        </x-modal>

        <x-modal name="cancel-wo" title="Cancel This Work Order" icon="x-circle" tone="danger" subtitle="This cannot be undone.">
            <form method="POST" action="{{ route('shop.account.work-order.cancel', $workOrder) }}" id="cancel-wo-form">
                @csrf
                <x-field label="Reason (Optional)" for="reason">
                    <textarea id="reason" name="reason" rows="3" maxlength="500"
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                        placeholder="Let us know why, if you like..."></textarea>
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'cancel-wo')">Keep It</x-button>
                <x-button variant="danger" type="submit" form="cancel-wo-form">Cancel Work Order</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

</x-layouts.shop>
