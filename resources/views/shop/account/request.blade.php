<x-layouts.shop :title="'Request ' . $request->number">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <a href="{{ route('shop.account.requests') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-shop-muted hover:text-shop-ink transition">
            <x-icon name="chevron-left" class="w-4 h-4" /> Back To Requests
        </a>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-3xl font-semibold tracking-tight text-shop-ink">{{ $request->subject }}</h1>
                <p class="mt-1 text-shop-muted">{{ $request->number }} &middot; Submitted {{ $request->created_at->format('F j, Y') }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <x-badge :color="$request->priority_badge" dot>{{ ucwords($request->priority) }}</x-badge>
                <x-badge :color="$request->status_badge" dot>{{ $request->status_label }}</x-badge>
            </div>
        </div>
    </section>

    <div class="section-divider"></div>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

            <div class="min-w-0 lg:col-span-2 space-y-8">
                <div>
                    <h2 class="text-lg font-semibold text-shop-ink mb-3">Details</h2>
                    <div class="rounded-xl ring-1 ring-inset ring-shop-line bg-white p-5">
                        <dl class="space-y-3 text-sm">
                            @if ($request->service)
                                <div class="flex justify-between gap-4"><dt class="text-shop-muted">Service</dt><dd class="text-shop-ink text-right">{{ $request->service->name }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-4"><dt class="text-shop-muted">Contact</dt><dd class="text-shop-ink text-right">{{ $request->name }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-shop-muted">Email</dt><dd class="text-shop-ink text-right break-all">{{ $request->email }}</dd></div>
                            @if ($request->phone)
                                <div class="flex justify-between gap-4"><dt class="text-shop-muted">Phone</dt><dd class="text-shop-ink text-right">{{ $request->phone }}</dd></div>
                            @endif
                        </dl>
                        @if ($request->description)
                            <div class="mt-4 pt-4 border-t border-shop-line">
                                <p class="text-sm text-shop-ink whitespace-pre-line leading-relaxed">{{ $request->description }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($request->address)
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-2">Service Address</h2>
                        <address class="not-italic text-sm text-shop-muted leading-relaxed">
                            {{ $request->address['line1'] ?? '' }}<br>
                            @if (! empty($request->address['line2'])){{ $request->address['line2'] }}<br>@endif
                            {{ $request->address['city'] ?? '' }}@if (! empty($request->address['state'])), {{ $request->address['state'] }}@endif {{ $request->address['postcode'] ?? '' }}
                        </address>
                    </div>
                @endif

                @if ($request->attachments->isNotEmpty())
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-3">Photos &amp; Attachments</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($request->attachments as $attachment)
                                <span class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm text-shop-ink ring-1 ring-inset ring-shop-line">
                                    <x-icon name="download" class="w-4 h-4 text-shop-muted" />
                                    <span class="truncate max-w-[12rem]">{{ $attachment->filename }}</span>
                                    <span class="text-xs text-shop-muted">{{ $attachment->size_formatted }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($request->ticket || $request->workOrder)
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-3">Linked Items</h2>
                        <div class="space-y-2">
                            @if ($request->ticket)
                                <a href="{{ route('shop.account.ticket', $request->ticket) }}" class="flex items-center justify-between gap-3 rounded-lg bg-white px-4 py-3 ring-1 ring-inset ring-shop-line transition hover:bg-slate-50">
                                    <span class="inline-flex items-center gap-2 text-sm font-medium text-shop-ink"><x-icon name="envelope" class="w-4 h-4 text-shop-muted" /> Ticket {{ $request->ticket->number }}</span>
                                    <x-icon name="chevron-right" class="w-4 h-4 text-shop-muted" />
                                </a>
                            @endif
                            @if ($request->workOrder)
                                <a href="{{ route('shop.account.work-order', $request->workOrder) }}" class="flex items-center justify-between gap-3 rounded-lg bg-white px-4 py-3 ring-1 ring-inset ring-shop-line transition hover:bg-slate-50">
                                    <span class="inline-flex items-center gap-2 text-sm font-medium text-shop-ink"><x-icon name="clock" class="w-4 h-4 text-shop-muted" /> Work Order {{ $request->workOrder->number }}</span>
                                    <x-icon name="chevron-right" class="w-4 h-4 text-shop-muted" />
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div>
                <x-card title="Activity">
                    <x-account-timeline :activities="$request->activities" />
                </x-card>
            </div>
        </div>
    </section>

</x-layouts.shop>
