<x-layouts.shop :title="'Ticket ' . $ticket->number">

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-6">
        <a href="{{ route('shop.account.tickets') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-shop-muted hover:text-shop-ink transition">
            <x-icon name="chevron-left" class="w-4 h-4" /> Back To Tickets
        </a>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-3xl font-semibold tracking-tight text-shop-ink">{{ $ticket->subject }}</h1>
                <p class="mt-1 text-shop-muted">{{ $ticket->number }} &middot; Opened {{ $ticket->created_at->format('F j, Y') }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <x-badge :color="$ticket->priority_badge" dot>{{ $ticket->priority_label }}</x-badge>
                <x-badge :color="$ticket->status_badge" dot>{{ $ticket->status_label }}</x-badge>
            </div>
        </div>
    </section>

    <div class="section-divider"></div>

    <section class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

            <div class="min-w-0 lg:col-span-2 space-y-8">
                {{-- Conversation --}}
                <div>
                    <h2 class="text-lg font-semibold text-shop-ink mb-4">Conversation</h2>

                    @if ($ticket->description)
                        <div class="mb-4 rounded-xl ring-1 ring-inset ring-shop-line bg-white p-4">
                            <p class="text-xs font-medium text-shop-muted mb-1">Original Request</p>
                            <p class="text-sm text-shop-ink whitespace-pre-line leading-relaxed">{{ $ticket->description }}</p>
                        </div>
                    @endif

                    <div class="space-y-4">
                        @forelse ($ticket->replies as $reply)
                            @if ($reply->is_staff)
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-200 text-xs font-semibold">
                                        <x-icon name="user" class="w-4 h-4" />
                                    </span>
                                    <div class="min-w-0 max-w-[85%]">
                                        <div class="rounded-2xl rounded-tl-sm bg-brand-50 px-4 py-3 ring-1 ring-inset ring-brand-100">
                                            <p class="text-sm text-shop-ink whitespace-pre-line leading-relaxed">{{ $reply->body }}</p>
                                        </div>
                                        <p class="mt-1 px-1 text-xs text-shop-muted">{{ $reply->author_label }} &middot; {{ $reply->created_at->format('F j, Y g:i A') }}</p>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-start justify-end gap-3">
                                    <div class="min-w-0 max-w-[85%]">
                                        <div class="rounded-2xl rounded-tr-sm bg-white px-4 py-3 ring-1 ring-inset ring-shop-line">
                                            <p class="text-sm text-shop-ink whitespace-pre-line leading-relaxed">{{ $reply->body }}</p>
                                        </div>
                                        <p class="mt-1 px-1 text-right text-xs text-shop-muted">{{ $reply->author_label }} &middot; {{ $reply->created_at->format('F j, Y g:i A') }}</p>
                                    </div>
                                    <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200 text-xs font-semibold">
                                        {{ auth('customer')->user()->initials }}
                                    </span>
                                </div>
                            @endif
                        @empty
                            <p class="text-sm text-shop-muted">No replies yet. Our team will respond soon.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Reply box --}}
                <div>
                    @if ($ticket->status === 'closed')
                        <div class="rounded-xl bg-slate-50 px-4 py-4 ring-1 ring-inset ring-slate-200 text-sm text-shop-muted">
                            This ticket is closed. If you still need help, please <a href="{{ route('shop.request') }}" class="font-medium text-brand-700 hover:text-brand-800">submit a new request</a>.
                        </div>
                    @else
                        <form method="POST" action="{{ route('shop.account.ticket.reply', $ticket) }}" class="rounded-xl ring-1 ring-inset ring-shop-line bg-white p-4">
                            @csrf
                            <x-field label="Add A Reply" for="body" required :error="$errors->first('body')">
                                <textarea id="body" name="body" rows="4" required maxlength="5000"
                                    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                                    placeholder="Type your message...">{{ old('body') }}</textarea>
                            </x-field>
                            <div class="mt-3 flex justify-end">
                                <x-button type="submit" icon="envelope">Send Reply</x-button>
                            </div>
                        </form>
                    @endif
                </div>

                @if ($ticket->attachments->isNotEmpty())
                    <div>
                        <h2 class="text-lg font-semibold text-shop-ink mb-3">Attachments</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($ticket->attachments as $attachment)
                                <span class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm text-shop-ink ring-1 ring-inset ring-shop-line">
                                    <x-icon name="download" class="w-4 h-4 text-shop-muted" />
                                    <span class="truncate max-w-[12rem]">{{ $attachment->filename }}</span>
                                    <span class="text-xs text-shop-muted">{{ $attachment->size_formatted }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div>
                <x-card title="Activity">
                    <x-account-timeline :activities="$ticket->activities" />
                </x-card>
            </div>
        </div>
    </section>

</x-layouts.shop>
