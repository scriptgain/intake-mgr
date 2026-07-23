{{-- Shared quote detail body. Expects:
       $quote        the Quote
       $acceptAction / $declineAction  route URLs for the accept/decline forms
       $payUrl       (optional) signed Pay link when an invoice exists and is due
--}}
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-600">Quote {{ $quote->number }}</p>
            <h2 class="mt-1 text-xl font-semibold text-shop-ink">{{ $quote->title }}</h2>
            @if ($quote->valid_until)
                <p class="mt-1 text-sm text-shop-muted">Valid until {{ $quote->valid_until->format('F j, Y') }}</p>
            @endif
        </div>
        <x-badge :color="$quote->status_badge" dot>{{ $quote->status_label }}</x-badge>
    </div>

    <div class="px-6 py-5">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-shop-muted">
                    <th class="pb-2 font-medium">Service</th>
                    <th class="pb-2 text-right font-medium">Qty</th>
                    <th class="pb-2 text-right font-medium">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($quote->items as $item)
                    <tr>
                        <td class="py-2.5 pr-3 text-shop-ink">{{ $item->name }}</td>
                        <td class="py-2.5 text-right tabular text-shop-muted">{{ $item->quantity }}</td>
                        <td class="py-2.5 text-right tabular font-medium text-shop-ink">{{ $item->total_formatted }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <dl class="mt-4 space-y-2 border-t border-slate-200 pt-4 text-sm">
            <div class="flex justify-between"><dt class="text-shop-muted">Subtotal</dt><dd class="tabular text-shop-ink">{{ $quote->subtotal_formatted }}</dd></div>
            @if ($quote->discount_cents > 0)
                <div class="flex justify-between"><dt class="text-shop-muted">Discount</dt><dd class="tabular text-emerald-600">&minus;{{ $quote->discount_formatted }}</dd></div>
            @endif
            @if ($quote->tax_cents > 0)
                <div class="flex justify-between"><dt class="text-shop-muted">Tax</dt><dd class="tabular text-shop-ink">{{ $quote->tax_formatted }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt class="text-shop-ink">Total</dt><dd class="tabular text-shop-ink">{{ $quote->total_formatted }}</dd></div>
        </dl>

        @if ($quote->message)
            <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-shop-ink whitespace-pre-line">{{ $quote->message }}</div>
        @endif
    </div>

    @if ($quote->is_actionable)
        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
            <x-button variant="secondary" icon="x-circle" x-data @click="$dispatch('open-modal', 'decline-quote')">Decline</x-button>
            <x-confirm-action name="accept-quote" :action="$acceptAction"
                title="Accept This Quote?" tone="default" confirm="Accept Quote" confirmIcon="check-circle"
                message="Accepting confirms the price and scope above. We will follow up to schedule the work and send your invoice.">
                <x-button icon="check-circle">Accept Quote</x-button>
            </x-confirm-action>
        </div>

        <x-modal name="decline-quote" title="Decline This Quote?" icon="warning" tone="danger" maxWidth="max-w-md">
            We will mark this quote declined. If you would like to tell us why, you can add a note.
            <form id="decline-quote-form" method="POST" action="{{ $declineAction }}" class="mt-4">
                @csrf
                <x-field label="Reason" for="decline-reason" hint="Optional.">
                    <x-input id="decline-reason" name="reason" placeholder="Optional" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'decline-quote')">Keep Quote</x-button>
                <x-button variant="danger" size="sm" type="submit" form="decline-quote-form" icon="x-circle">Decline Quote</x-button>
            </x-slot:footer>
        </x-modal>
    @elseif ($quote->status === 'accepted')
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-emerald-50 px-6 py-4">
            <p class="text-sm font-medium text-emerald-800">You accepted this quote. Thank you.</p>
            @if (($payUrl ?? null))
                <x-button href="{{ $payUrl }}" icon="credit-card">Pay Invoice</x-button>
            @endif
        </div>
    @elseif ($quote->status === 'declined')
        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
            <p class="text-sm font-medium text-shop-muted">This quote was declined.</p>
        </div>
    @endif
</div>
