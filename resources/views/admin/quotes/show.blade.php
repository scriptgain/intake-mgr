<x-layouts.app :title="$quote->number">
    <x-page-header
        eyebrow="Quote"
        :title="$quote->number"
        icon="document"
        :subtitle="$quote->title"
        :back="['href' => route('quotes.index'), 'label' => 'All Quotes']">
        <x-slot:meta>
            <x-badge :color="$quote->status_badge" dot>{{ $quote->status_label }}</x-badge>
            @if ($quote->valid_until)
                <x-badge :color="$quote->is_expired ? 'warn' : 'neutral'">Valid Until {{ $quote->valid_until->format(config('shop.date_format', 'M j, Y')) }}</x-badge>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            @if (! in_array($quote->status, ['converted'], true))
                <x-button variant="secondary" size="sm" icon="edit" href="{{ route('quotes.edit', $quote) }}">Edit</x-button>
            @endif
            @if (in_array($quote->status, ['draft', 'sent', 'expired'], true))
                <x-confirm-action name="send-quote" :action="route('quotes.send', $quote)"
                    title="Send This Quote?" tone="default" confirm="Send Quote" confirmIcon="envelope"
                    message="The quote is marked Sent and emailed to the customer with a link to accept or decline it online.">
                    <x-button variant="secondary" size="sm" icon="envelope">{{ $quote->status === 'draft' ? 'Send' : 'Resend' }}</x-button>
                </x-confirm-action>
            @endif
            <x-delete-button :action="route('quotes.destroy', $quote)" name="delete-quote"
                label="Delete Quote" title="Delete This Quote?"
                message="This permanently removes the quote and its line items. Any generated invoice or work order keeps its history. This cannot be undone." />
        </x-slot:actions>

        <x-slot:primary>
            @if ($quote->is_actionable)
                <x-confirm-action name="accept-quote" :action="route('quotes.accept', $quote)"
                    title="Accept This Quote?" tone="default" confirm="Mark Accepted" confirmIcon="check-circle"
                    message="The quote is marked Accepted on the customer's behalf. If auto-invoicing is on, an invoice is generated now for them to pay.">
                    <x-button size="sm" icon="check-circle">Mark Accepted</x-button>
                </x-confirm-action>
            @elseif ($quote->is_convertible)
                <x-button size="sm" icon="refresh" x-data @click="$dispatch('open-modal', 'convert-quote')">Convert</x-button>
            @endif
        </x-slot:primary>
    </x-page-header>

    {{-- Status stepper: where this quote sits in its lifecycle. --}}
    <div class="mb-6">
        <x-segmented label="Quote Status">
            @foreach (['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'converted' => 'Converted'] as $key => $label)
                <span class="vx-seg-item {{ $quote->status === $key ? 'is-active' : '' }}">{{ $label }}</span>
            @endforeach
            @if (in_array($quote->status, ['declined', 'expired'], true))
                <span class="vx-seg-item is-active">{{ $quote->status_label }}</span>
            @endif
        </x-segmented>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">
            <x-card title="Line Items" flush>
                @if ($quote->items->isEmpty())
                    <x-empty-state icon="tag" title="No Line Items"
                        description="This quote has no services on it yet. Edit it to add what you are proposing and the prices." />
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quote->items as $item)
                                <tr>
                                    <td>
                                        <span class="font-medium text-slate-900">{{ $item->name }}</span>
                                        @if ($item->service)<span class="block text-xs text-slate-400">{{ $item->service->name }}</span>@endif
                                    </td>
                                    <td class="tabular text-right">{{ $item->quantity }}</td>
                                    <td class="tabular text-right text-slate-500">{{ $item->unit_price_formatted }}</td>
                                    <td class="text-right font-semibold text-slate-900">{{ $item->total_formatted }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                    <dl class="space-y-2 border-t border-slate-200 px-5 py-4 text-sm sm:px-6">
                        <div class="flex items-center justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular text-slate-900">{{ $quote->subtotal_formatted }}</dd></div>
                        @if ($quote->discount_cents > 0)
                            <div class="flex items-center justify-between"><dt class="text-slate-500">Discount</dt><dd class="tabular text-emerald-600">&minus;{{ $quote->discount_formatted }}</dd></div>
                        @endif
                        @if ($quote->tax_cents > 0)
                            <div class="flex items-center justify-between"><dt class="text-slate-500">Tax</dt><dd class="tabular text-slate-900">{{ $quote->tax_formatted }}</dd></div>
                        @endif
                        <div class="flex items-center justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt class="text-slate-900">Total</dt><dd class="tabular text-slate-900">{{ $quote->total_formatted }}</dd></div>
                    </dl>
                @endif
            </x-card>

            @if ($quote->message)
                <x-card title="Message To Customer">
                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $quote->message }}</p>
                </x-card>
            @endif

            <x-card title="Activity">
                @include('admin._activity-timeline', ['activities' => $quote->activities])
            </x-card>
        </div>

        <div class="space-y-4">
            @if ($quote->is_actionable)
                <x-card title="Decision">
                    <p class="text-sm text-slate-600">Waiting on the customer. You can also record their decision here.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-confirm-action name="accept-quote-side" :action="route('quotes.accept', $quote)"
                            title="Accept This Quote?" tone="default" confirm="Mark Accepted" confirmIcon="check-circle"
                            message="The quote is marked Accepted on the customer's behalf.">
                            <x-button size="sm" variant="secondary" icon="check-circle">Accepted</x-button>
                        </x-confirm-action>
                        <x-button size="sm" variant="secondary" icon="x-circle" x-data @click="$dispatch('open-modal', 'decline-quote')">Declined</x-button>
                    </div>
                </x-card>
            @endif

            <x-card title="Customer">
                @if ($quote->customer)
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-brand-200">
                            {{ $quote->customer->initials }}
                        </span>
                        <div class="min-w-0">
                            <a href="{{ route('customers.show', $quote->customer) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $quote->customer->name }}</a>
                            <a href="mailto:{{ $quote->customer->email }}" class="block truncate text-xs text-slate-500 hover:text-brand-700">{{ $quote->customer->email }}</a>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-slate-500">No customer is linked to this quote.</p>
                @endif
            </x-card>

            @if (! empty($addressLines))
                <x-card title="Service Address">
                    <address class="space-y-0.5 text-sm not-italic text-slate-600">
                        @foreach ($addressLines as $line)<p>{{ $line }}</p>@endforeach
                    </address>
                </x-card>
            @endif

            @if ($quote->invoice || $quote->workOrder || $quote->serviceRequest || $quote->project)
                <x-card title="Linked">
                    <dl class="space-y-2.5 text-sm">
                        @if ($quote->serviceRequest)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Request</dt>
                                <dd><a href="{{ route('service-requests.show', $quote->serviceRequest) }}" class="font-medium text-brand-700 hover:underline">{{ $quote->serviceRequest->number }}</a></dd>
                            </div>
                        @endif
                        @if ($quote->invoice)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Invoice</dt>
                                <dd><a href="{{ route('orders.show', $quote->invoice) }}" class="font-medium text-brand-700 hover:underline">{{ $quote->invoice->number }}</a></dd>
                            </div>
                        @endif
                        @if ($quote->workOrder)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Work Order</dt>
                                <dd><a href="{{ route('work-orders.show', $quote->workOrder) }}" class="font-medium text-brand-700 hover:underline">{{ $quote->workOrder->number }}</a></dd>
                            </div>
                        @endif
                        @if ($quote->project)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Project</dt>
                                <dd><a href="{{ route('projects.show', $quote->project) }}" class="font-medium text-brand-700 hover:underline">{{ $quote->project->number }}</a></dd>
                            </div>
                        @endif
                    </dl>
                </x-card>
            @endif
        </div>
    </div>

    {{-- Decline modal --}}
    <x-modal name="decline-quote" title="Decline This Quote?" icon="warning" tone="danger" maxWidth="max-w-md">
        The quote moves to Declined. You can record why below.
        <form id="decline-quote-form" method="POST" action="{{ route('quotes.decline', $quote) }}" class="mt-4">
            @csrf
            <x-field label="Reason" for="decline-reason" hint="Optional. Shown in the timeline.">
                <x-input id="decline-reason" name="reason" placeholder="Optional" />
            </x-field>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'decline-quote')">Keep Quote</x-button>
            <x-button variant="danger" size="sm" type="submit" form="decline-quote-form" icon="x-circle">Decline Quote</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Convert modal: choose what to create from the accepted quote. --}}
    @if ($quote->is_convertible)
        <x-modal name="convert-quote" title="Convert This Quote" icon="info" tone="default" maxWidth="max-w-md">
            <p class="text-sm text-slate-600">Create the records to fulfil this accepted quote. Anything that already exists is left as is.</p>
            <div class="mt-4 space-y-2.5">
                @unless ($quote->invoice)
                    <form method="POST" action="{{ route('quotes.convert', $quote) }}">
                        @csrf
                        <input type="hidden" name="targets[]" value="invoice">
                        <input type="hidden" name="targets[]" value="work_order">
                        <x-button type="submit" icon="refresh" class="w-full justify-center">Create Invoice And Work Order</x-button>
                    </form>
                    <form method="POST" action="{{ route('quotes.convert', $quote) }}">
                        @csrf
                        <input type="hidden" name="targets[]" value="invoice">
                        <x-button type="submit" variant="secondary" icon="credit-card" class="w-full justify-center">Create Invoice Only</x-button>
                    </form>
                @endunless
                @unless ($quote->workOrder)
                    <form method="POST" action="{{ route('quotes.convert', $quote) }}">
                        @csrf
                        <input type="hidden" name="targets[]" value="work_order">
                        <x-button type="submit" variant="secondary" icon="truck" class="w-full justify-center">Create Work Order{{ $quote->invoice ? '' : ' Only' }}</x-button>
                    </form>
                @endunless
            </div>
            <x-slot:footer>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'convert-quote')">Cancel</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</x-layouts.app>
