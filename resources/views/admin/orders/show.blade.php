<x-layouts.app :title="$order->number">
    <div x-data="{ tab: 'items' }">
        <x-page-header
            eyebrow="Order"
            :title="$order->number"
            icon="credit-card"
            :subtitle="'Placed ' . $order->created_at?->diffForHumans() . ' by ' . $order->customer_name"
            :back="['href' => route('orders.index'), 'label' => 'All Orders']">
            {{-- Status belongs with the identity of the order, not floating in a
                 separate strip below the header. --}}
            <x-slot:meta>
                <x-badge :color="$order->status_badge" dot>{{ \Illuminate\Support\Str::headline($order->status) }}</x-badge>
                <x-badge :color="$order->financial_badge" dot>{{ \Illuminate\Support\Str::headline($order->financial_status) }}</x-badge>
                <x-badge :color="$order->fulfillment_badge" dot>{{ \Illuminate\Support\Str::headline($order->fulfillment_status) }}</x-badge>
                @if ($order->is_test_payment)
                    {{-- A test-mode charge must never be mistakable for revenue. --}}
                    <x-badge color="warn" dot>Test Payment</x-badge>
                @endif
            </x-slot:meta>

            <x-slot:actions>
                @if ($order->is_paid && $order->refundable_cents > 0)
                    <x-button variant="secondary" size="sm" icon="refresh"
                        x-data @click="$dispatch('open-modal', 'refund-order')">Refund</x-button>
                @endif
                <x-button variant="secondary" size="sm" icon="envelope"
                    x-data @click="$dispatch('open-modal', 'resend-email')">Resend Email</x-button>
                @if ($order->is_actionable)
                    <x-button variant="secondary" size="sm" icon="x-circle"
                        x-data @click="$dispatch('open-modal', 'cancel-order')">Cancel Order</x-button>
                @endif
            </x-slot:actions>

            {{-- The single most likely next action, whatever state the order is
                 in: take the money, then ship the goods. --}}
            <x-slot:primary>
                @if (! $order->is_paid && $order->is_actionable)
                    <x-button size="sm" icon="credit-card" x-data @click="$dispatch('open-modal', 'mark-paid')">Mark As Paid</x-button>
                @elseif ($order->is_actionable && ! $order->is_fully_fulfilled && $fulfillableItems->isNotEmpty())
                    <x-button size="sm" icon="truck" @click="tab = 'fulfillment'">Fulfill Items</x-button>
                @endif
            </x-slot:primary>
        </x-page-header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="min-w-0 space-y-4 lg:col-span-2">
                <x-segmented label="Order Sections">
                    <button type="button" role="tab" :aria-selected="(tab === 'items').toString()" @click="tab = 'items'"
                        class="vx-seg-item" :class="tab === 'items' && 'is-active'">Items
                        <span class="vx-seg-count">{{ $order->items->count() }}</span>
                    </button>
                    <button type="button" role="tab" :aria-selected="(tab === 'fulfillment').toString()" @click="tab = 'fulfillment'"
                        class="vx-seg-item" :class="tab === 'fulfillment' && 'is-active'">Fulfillment
                        <span class="vx-seg-count">{{ $order->fulfillments->count() }}</span>
                    </button>
                    <button type="button" role="tab" :aria-selected="(tab === 'timeline').toString()" @click="tab = 'timeline'"
                        class="vx-seg-item" :class="tab === 'timeline' && 'is-active'">Timeline
                        <span class="vx-seg-count">{{ $order->events->count() }}</span>
                    </button>
                </x-segmented>

                {{-- Items --}}
                <div x-show="tab === 'items'" x-cloak>
                    <x-card flush>
                        <x-table flush>
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Total</th>
                                    <th>Shipping</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr class="vx-rail {{ $item->requires_shipping && $item->unfulfilled_qty > 0 ? 'vx-rail-warn' : 'vx-rail-none' }}">
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-300 ring-1 ring-slate-200">
                                                    @if ($item->image_url)
                                                        <img src="{{ $item->image_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                                    @else
                                                        <x-icon name="bag" class="h-4 w-4" aria-hidden="true" />
                                                    @endif
                                                </span>
                                                <div class="min-w-0">
                                                    <span class="block truncate font-medium text-slate-900">{{ $item->name }}</span>
                                                    @if ($item->variant_label)<span class="block text-xs text-slate-500">{{ $item->variant_label }}</span>@endif
                                                    @if ($item->sku)<span class="block font-mono text-xs text-slate-400">{{ $item->sku }}</span>@endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="tabular text-right">{{ $item->quantity }}</td>
                                        <td class="tabular text-right text-slate-500">{{ $item->unit_price_formatted }}</td>
                                        <td class="text-right font-semibold text-slate-900">{{ $item->total_formatted }}</td>
                                        <td>
                                            @if (! $item->requires_shipping)
                                                <x-badge color="neutral">No Shipping</x-badge>
                                            @elseif ($item->unfulfilled_qty <= 0)
                                                <x-badge color="success" dot>Fulfilled</x-badge>
                                            @elseif ($item->fulfilled_qty > 0)
                                                <x-badge color="warn" dot>{{ $item->fulfilled_qty }}/{{ $item->quantity }} Shipped</x-badge>
                                            @else
                                                <x-badge color="warn" dot>Unfulfilled</x-badge>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table>
                    </x-card>
                </div>

                {{-- Fulfillment --}}
                <div x-show="tab === 'fulfillment'" x-cloak class="space-y-6">
                    @if ($order->is_actionable && $fulfillableItems->isNotEmpty())
                        <x-card title="Create A Shipment" subtitle="Ship all of it, or part of it and the rest later.">
                            <form method="POST" action="{{ route('orders.fulfill', $order) }}" class="space-y-5">
                                @csrf
                                <div class="divide-y divide-slate-100 border-y border-slate-100">
                                    @foreach ($fulfillableItems as $item)
                                        <div class="flex items-center justify-between gap-4 py-3">
                                            <div class="min-w-0">
                                                <label for="qty-{{ $item->id }}" class="block truncate text-sm font-medium text-slate-900">{{ $item->name }}</label>
                                                <p class="text-xs text-slate-500">{{ $item->variant_label ?? 'Default' }} &middot; {{ $item->unfulfilled_qty }} Remaining</p>
                                            </div>
                                            <input id="qty-{{ $item->id }}" type="number" name="quantities[{{ $item->id }}]"
                                                value="{{ old('quantities.'.$item->id, $item->unfulfilled_qty) }}"
                                                min="0" max="{{ $item->unfulfilled_qty }}"
                                                class="tabular w-24 shrink-0 rounded-lg border-0 bg-white px-3 py-2 text-right text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                        </div>
                                    @endforeach
                                </div>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    <x-field label="Carrier" for="carrier">
                                        <x-select id="carrier" name="carrier">
                                            <option value="">Select Carrier</option>
                                            @foreach ($carriers as $carrier)
                                                <option value="{{ $carrier }}" @selected(old('carrier') === $carrier)>{{ $carrier }}</option>
                                            @endforeach
                                        </x-select>
                                    </x-field>
                                    <x-field label="Tracking Number" for="tracking_number">
                                        <x-input id="tracking_number" name="tracking_number" :value="old('tracking_number')" />
                                    </x-field>
                                    <x-field label="Tracking URL" for="tracking_url" hint="Optional. Overrides the carrier's default lookup link." class="sm:col-span-2">
                                        <x-input id="tracking_url" name="tracking_url" type="url" :value="old('tracking_url')" placeholder="https://" />
                                    </x-field>
                                </div>
                                <x-toggle name="notify_customer" :checked="true" label="Notify Customer" description="Email the shopper that their order has shipped." />
                                <div class="flex justify-end pt-2">
                                    <x-button type="submit" icon="truck">Create Shipment</x-button>
                                </div>
                            </form>
                        </x-card>
                    @endif

                    <x-card title="Shipments" flush>
                        @if ($order->fulfillments->isEmpty())
                            <x-empty-state icon="truck" title="Nothing Shipped Yet"
                                description="Each shipment you create is recorded here with its carrier and tracking number, so you can see exactly what left and when." />
                        @else
                            <x-table flush>
                                <thead>
                                    <tr>
                                        <th>Status</th><th>Carrier</th><th>Tracking</th><th class="text-right">Items</th><th class="text-right">Shipped</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->fulfillments as $fulfillment)
                                        <tr>
                                            <td><x-badge :color="$fulfillment->status_badge" dot>{{ \Illuminate\Support\Str::headline($fulfillment->status) }}</x-badge></td>
                                            <td class="text-slate-600">{{ $fulfillment->carrier ?: 'Not set' }}</td>
                                            <td>
                                                @if ($fulfillment->tracking_link)
                                                    <a href="{{ $fulfillment->tracking_link }}" target="_blank" rel="noopener" class="font-mono text-xs text-brand-700 hover:underline">{{ $fulfillment->tracking_number }}</a>
                                                @else
                                                    <span class="text-slate-400">None</span>
                                                @endif
                                            </td>
                                            <td class="tabular text-right">{{ $fulfillment->quantity }}</td>
                                            <td class="text-right text-slate-500">{{ $fulfillment->shipped_at?->diffForHumans() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-table>
                        @endif
                    </x-card>
                </div>

                {{-- Timeline --}}
                <div x-show="tab === 'timeline'" x-cloak>
                    <x-card>
                        @if ($order->events->isEmpty())
                            <x-empty-state icon="clock" title="No Activity Recorded"
                                description="Payments, shipments, refunds, and staff notes are all logged here in order, so there is one place to see what happened to this order and who did it." />
                        @else
                            {{-- Connected timeline: a hairline runs behind the markers so
                                 the events read as one sequence rather than a list. --}}
                            <ol class="relative space-y-5 before:absolute before:bottom-4 before:left-4 before:top-4 before:w-px before:bg-slate-200">
                                @foreach ($order->events as $event)
                                    <li class="relative flex gap-3.5">
                                        <span @class([
                                            'relative z-10 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white',
                                            'bg-emerald-50 text-emerald-600' => $event->tone === 'success',
                                            'bg-amber-50 text-amber-600' => $event->tone === 'warn',
                                            'bg-rose-50 text-rose-600' => $event->tone === 'danger',
                                            'bg-slate-100 text-slate-500' => $event->tone === 'neutral',
                                        ])>
                                            <x-icon :name="$event->icon" class="h-4 w-4" aria-hidden="true" />
                                        </span>
                                        <div class="min-w-0 pt-1">
                                            <p class="text-sm text-slate-900">{{ $event->message }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $event->actor }} &middot; {{ $event->created_at?->diffForHumans() }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </x-card>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-4">
                {{-- Totals lead the sidebar: the amount is the thing most often
                     checked against a payment processor or a customer email. --}}
                <x-card title="Totals">
                    <dl class="space-y-2 text-sm">
                        <div class="flex items-center justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular text-slate-900">{{ $order->subtotal_formatted }}</dd></div>
                        @if ($order->discount_cents > 0)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500">Discount {{ $order->discount_code ? '(' . $order->discount_code . ')' : '' }}</dt>
                                <dd class="tabular text-emerald-700">&minus;{{ $order->discount_formatted }}</dd>
                            </div>
                        @endif
                        <div class="flex items-center justify-between"><dt class="text-slate-500">Shipping</dt><dd class="tabular text-slate-900">{{ $order->shipping_formatted }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-slate-500">Tax</dt><dd class="tabular text-slate-900">{{ $order->tax_formatted }}</dd></div>
                        <div class="flex items-baseline justify-between border-t border-slate-200 pt-3">
                            <dt class="font-medium text-slate-900">Total</dt>
                            <dd><x-money :formatted="$order->total_formatted" size="md" class="text-slate-900" /></dd>
                        </div>
                        @if ($order->refunded_cents > 0)
                            <div class="flex items-center justify-between"><dt class="text-rose-600">Refunded</dt><dd class="tabular text-rose-600">&minus;{{ $order->refunded_formatted }}</dd></div>
                        @endif
                    </dl>
                </x-card>

                {{-- Payment. Brand and last four are the only card details
                     ShopMGR stores; there is nothing else here to show. --}}
                <x-card title="Payment">
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Method</dt>
                            <dd class="text-slate-900">{{ $order->payment_gateway === 'stripe' ? 'Card (Stripe)' : 'Manual / Offline' }}</dd>
                        </div>
                        @if ($order->card_label)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500">Card</dt>
                                <dd class="inline-flex items-center gap-2 text-slate-900">
                                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded bg-slate-100 text-slate-600 ring-1 ring-slate-200">
                                        <x-icon name="credit-card" class="h-3.5 w-3.5" />
                                    </span>
                                    {{ $order->card_label }}
                                </dd>
                            </div>
                        @endif
                        @if ($order->paid_at)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500">Paid</dt>
                                <dd class="text-slate-900">{{ $order->paid_at->format(config('shop.date_format').' '.config('shop.time_format')) }}</dd>
                            </div>
                        @endif
                        @if ($order->payment_reference)
                            <div class="flex items-start justify-between gap-3">
                                <dt class="shrink-0 text-slate-500">Reference</dt>
                                <dd class="min-w-0 break-all text-right font-mono text-xs text-slate-600">{{ $order->payment_reference }}</dd>
                            </div>
                        @endif
                        @if ($order->payment_failure_reason)
                            <div class="border-t border-slate-100 pt-3">
                                <dt class="vx-eyebrow mb-1 text-rose-600">Last Failure</dt>
                                <dd class="text-sm text-rose-700">{{ $order->payment_failure_reason }}</dd>
                            </div>
                        @endif
                        @if ($order->is_test_payment)
                            <div class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 ring-1 ring-inset ring-amber-200">
                                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded bg-amber-100 text-amber-700 ring-1 ring-amber-200">
                                    <x-icon name="warning" class="h-3.5 w-3.5" />
                                </span>
                                <p class="text-xs text-amber-800">Taken in Stripe test mode. No real money moved.</p>
                            </div>
                        @endif
                    </dl>
                </x-card>

                <x-card title="Customer">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-brand-200">
                            {{ $order->customer?->initials ?? mb_strtoupper(mb_substr($order->email, 0, 2)) }}
                        </span>
                        <div class="min-w-0">
                            @if ($order->customer)
                                <a href="{{ route('customers.show', $order->customer) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $order->customer->name }}</a>
                            @else
                                <span class="block truncate font-medium text-slate-900">Guest Checkout</span>
                            @endif
                            <a href="mailto:{{ $order->email }}" class="block truncate text-xs text-slate-500 hover:text-brand-700">{{ $order->email }}</a>
                        </div>
                    </div>
                    @if ($order->phone)<p class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-600">{{ $order->phone }}</p>@endif
                </x-card>

                {{-- Both addresses share one card. They are usually identical, so
                     two full-height cards spent a lot of sidebar on one repeated
                     block and left the main column visibly short. --}}
                <x-card title="Addresses">
                    <div class="space-y-4">
                        <div>
                            <p class="vx-eyebrow mb-1.5">Shipping</p>
                            @if (empty($shippingLines))
                                <p class="text-sm text-slate-500">None on this order. Digital-only orders will not have one.</p>
                            @else
                                <address class="space-y-0.5 text-sm not-italic text-slate-600">
                                    @foreach ($shippingLines as $line)<p>{{ $line }}</p>@endforeach
                                </address>
                            @endif
                        </div>
                        <div class="border-t border-slate-100 pt-4">
                            <p class="vx-eyebrow mb-1.5">Billing</p>
                            @if (empty($billingLines))
                                <p class="text-sm text-slate-500">Same as shipping.</p>
                            @else
                                <address class="space-y-0.5 text-sm not-italic text-slate-600">
                                    @foreach ($billingLines as $line)<p>{{ $line }}</p>@endforeach
                                </address>
                            @endif
                        </div>
                    </div>
                </x-card>

                <x-card title="Staff Notes" subtitle="Internal. The shopper never sees these.">
                    <form method="POST" action="{{ route('orders.note', $order) }}" class="space-y-3">
                        @csrf
                        <label for="order-note" class="sr-only">Note</label>
                        <textarea id="order-note" name="message" rows="3" required placeholder="What happened, for whoever picks this up next"
                            class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"></textarea>
                        <div class="flex justify-end">
                            <x-button type="submit" size="sm" icon="edit">Add Note</x-button>
                        </div>
                    </form>
                </x-card>
            </div>
        </div>

        {{-- Action modals --}}
        <x-modal name="mark-paid" title="Mark This Order As Paid?" icon="credit-card" maxWidth="max-w-md">
            Records the order as paid without charging a card. Use this for cash, cheque, bank transfer, or any payment you took outside the store.
            <form id="mark-paid-form" method="POST" action="{{ route('orders.pay', $order) }}" class="mt-4">
                @csrf
                <x-field label="Reference" for="pay-reference" hint="Optional. Cheque number, wire reference, anything you need to reconcile later.">
                    <x-input id="pay-reference" name="reference" placeholder="Optional" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'mark-paid')">Cancel</x-button>
                <x-button variant="primary" size="sm" type="submit" form="mark-paid-form" icon="credit-card">Mark As Paid</x-button>
            </x-slot:footer>
        </x-modal>

        <x-modal name="refund-order" title="Refund This Order?" icon="refresh" tone="warn" maxWidth="max-w-md">
            @if ($order->is_stripe_refundable)
                {{ $order->refundable_formatted }} is available to refund. This sends the refund to Stripe and the money goes back to the customer's card, usually within five to ten business days.
            @else
                {{ $order->refundable_formatted }} is available to refund. This order was not paid by card, so this records the refund against the order only. You will need to return the money yourself.
            @endif

            <form id="refund-form" method="POST" action="{{ route('orders.refund', $order) }}" class="mt-4 space-y-4"
                  x-data="{ mode: 'full' }">
                @csrf

                {{-- Full is its own mode rather than the merchant retyping the
                     total, so a full refund can never land a cent short. --}}
                <x-field label="Amount" required>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input type="radio" name="mode" value="full" x-model="mode" class="text-brand-600 focus:ring-brand-500">
                            <span class="text-sm text-slate-700">Full Refund ({{ $order->refundable_formatted }})</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input type="radio" name="mode" value="partial" x-model="mode" class="text-brand-600 focus:ring-brand-500">
                            <span class="text-sm text-slate-700">Partial Refund</span>
                        </label>
                    </div>
                </x-field>

                <div x-show="mode === 'partial'" x-cloak>
                    <x-field label="Refund Amount" for="refund-amount" hint="Maximum {{ $order->refundable_formatted }}.">
                        <x-input id="refund-amount" name="amount" placeholder="0.00" />
                    </x-field>
                </div>

                <x-field label="Reason" for="refund-reason" hint="Optional. Sent to Stripe and shown in the order timeline.">
                    <x-select id="refund-reason" name="reason">
                        <option value="">No Reason Given</option>
                        <option value="requested_by_customer">Requested By Customer</option>
                        <option value="duplicate">Duplicate</option>
                        <option value="fraudulent">Fraudulent</option>
                    </x-select>
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'refund-order')">Cancel</x-button>
                <x-button variant="primary" size="sm" type="submit" form="refund-form" icon="refresh">
                    {{ $order->is_stripe_refundable ? 'Refund Via Stripe' : 'Record Refund' }}
                </x-button>
            </x-slot:footer>
        </x-modal>

        <x-modal name="resend-email" title="Resend Confirmation Email?" icon="envelope" tone="info" maxWidth="max-w-md">
            The order confirmation will be sent again to {{ $order->email }}, with the current contents and totals.
            <form id="resend-email-form" method="POST" action="{{ route('orders.resend-email', $order) }}" class="mt-4">
                @csrf
            </form>
            <x-slot:footer>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'resend-email')">Cancel</x-button>
                <x-button variant="primary" size="sm" type="submit" form="resend-email-form" icon="envelope">Resend Email</x-button>
            </x-slot:footer>
        </x-modal>

        <x-modal name="cancel-order" title="Cancel This Order?" icon="warning" tone="danger" maxWidth="max-w-md">
            The order moves to Cancelled and leaves the fulfillment queue. This cannot be undone.
            <form id="cancel-form" method="POST" action="{{ route('orders.cancel', $order) }}" class="mt-4 space-y-4">
                @csrf
                <x-field label="Reason" for="cancel-reason" hint="Optional. Shown in the order timeline.">
                    <x-input id="cancel-reason" name="reason" placeholder="Optional" />
                </x-field>
                <x-toggle name="restock" label="Restock Items" description="Return every unfulfilled quantity to inventory." />
            </form>
            <x-slot:footer>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'cancel-order')">Keep Order</x-button>
                <x-button variant="danger" size="sm" type="submit" form="cancel-form" icon="x-circle">Cancel Order</x-button>
            </x-slot:footer>
        </x-modal>
    </div>
</x-layouts.app>
