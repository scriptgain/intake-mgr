<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\QuoteMail;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Services\ServiceDesk\QuoteActions;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Quotes / estimates. Full CRUD plus the workflow: send to the customer, accept
 * or decline, and convert an accepted quote into an invoice and/or a work order.
 * Line items freeze the service name and price the same way work orders do.
 */
class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $quotes = Quote::with(['customer'])
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate((int) config('shop.rows_per_page', 25))
            ->withQueryString();

        $filters = $request->only(['q', 'status']);

        return view('admin.quotes.index', [
            'quotes' => $quotes,
            'filters' => $filters,
            'tabs' => $this->indexTabs($filters),
        ]);
    }

    private function indexTabs(array $filters): array
    {
        $counts = ['all' => Quote::count()];
        foreach (Quote::STATUSES as $status) {
            $counts[$status] = Quote::where('status', $status)->count();
        }

        $definitions = array_merge(
            [['key' => 'all', 'label' => 'All', 'status' => null]],
            array_map(fn ($s) => [
                'key' => $s,
                'label' => ucwords(str_replace('_', ' ', $s)),
                'status' => $s,
            ], Quote::STATUSES)
        );

        $active = $filters['status'] ?? null;
        $search = array_filter(['q' => $filters['q'] ?? null]);

        return array_map(fn ($tab) => [
            'label' => $tab['label'],
            'count' => $counts[$tab['key']] ?? 0,
            'active' => $tab['status'] === $active,
            'href' => route('quotes.index', array_merge(
                $tab['status'] ? ['status' => $tab['status']] : [],
                $search
            )),
        ], $definitions);
    }

    public function create()
    {
        return view('admin.quotes.create', [
            'quote' => new Quote(['status' => 'draft', 'currency' => config('shop.currency', 'USD')]),
            'customers' => Customer::orderBy('first_name')->orderBy('last_name')->get(),
            'serviceOptions' => $this->serviceOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateQuote($request);

        $quote = DB::transaction(function () use ($data, $request) {
            $quote = Quote::create([
                'customer_id' => $data['customer_id'] ?? null,
                'created_by' => auth()->id(),
                'title' => $data['title'],
                'message' => $data['message'] ?? null,
                'status' => $data['status'],
                'valid_until' => $data['valid_until'] ?? null,
                'discount_cents' => Money::parse($data['discount'] ?? null) ?? 0,
                'tax_cents' => Money::parse($data['tax'] ?? null) ?? 0,
                'address' => $this->addressFrom($request),
                'currency' => config('shop.currency', 'USD'),
            ]);

            $this->syncItems($quote, $request->input('items', []));
            $quote->recordActivity('created', 'Quote Created');

            return $quote;
        });

        return redirect()
            ->route('quotes.show', $quote)
            ->with('status', 'Quote '.$quote->number.' created.');
    }

    public function show(Quote $quote)
    {
        $quote->load(['customer', 'items.service', 'invoice', 'workOrder', 'serviceRequest', 'project', 'activities.user']);

        return view('admin.quotes.show', [
            'quote' => $quote,
            'addressLines' => $this->addressLines($quote->address),
        ]);
    }

    public function edit(Quote $quote)
    {
        $quote->load('items');

        return view('admin.quotes.edit', [
            'quote' => $quote,
            'customers' => Customer::orderBy('first_name')->orderBy('last_name')->get(),
            'serviceOptions' => $this->serviceOptions(),
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        $data = $this->validateQuote($request);

        DB::transaction(function () use ($data, $request, $quote) {
            $quote->forceFill([
                'customer_id' => $data['customer_id'] ?? null,
                'title' => $data['title'],
                'message' => $data['message'] ?? null,
                'status' => $data['status'],
                'valid_until' => $data['valid_until'] ?? null,
                'discount_cents' => Money::parse($data['discount'] ?? null) ?? 0,
                'tax_cents' => Money::parse($data['tax'] ?? null) ?? 0,
                'address' => $this->addressFrom($request),
            ])->save();

            $this->syncItems($quote, $request->input('items', []));
            $quote->recordActivity('note', 'Quote Updated');
        });

        return redirect()
            ->route('quotes.show', $quote)
            ->with('status', 'Quote updated.');
    }

    public function send(Quote $quote)
    {
        // Sending (re)opens the quote for the customer. Accepted/declined/
        // converted quotes are past that, so sending them makes no sense.
        if (! in_array($quote->status, ['draft', 'sent', 'expired'], true)) {
            return back()->with('warning', 'This quote can no longer be sent.');
        }

        $quote->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
        $quote->recordActivity('note', 'Quote Sent To Customer');

        $this->emailQuote($quote);

        return back()->with('status', 'Quote '.$quote->number.' sent.');
    }

    public function accept(Quote $quote)
    {
        QuoteActions::accept($quote);

        return back()->with('status', 'Quote marked accepted.');
    }

    public function decline(Request $request, Quote $quote)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        QuoteActions::decline($quote, $data['reason'] ?? null);

        return back()->with('status', 'Quote marked declined.');
    }

    public function convert(Request $request, Quote $quote)
    {
        if (! $quote->is_convertible) {
            return back()->with('warning', 'Only an accepted quote can be converted.');
        }

        $data = $request->validate([
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => [Rule::in(['invoice', 'work_order'])],
        ]);

        $created = QuoteActions::convert($quote, $data['targets']);

        $parts = [];
        if (isset($created['invoice'])) {
            $parts[] = 'invoice '.$created['invoice']->number;
        }
        if (isset($created['work_order'])) {
            $parts[] = 'work order '.$created['work_order']->number;
        }

        $message = $parts
            ? 'Quote converted: '.implode(' and ', $parts).'.'
            : 'Quote converted. Nothing new was created (targets already existed).';

        return back()->with('status', $message);
    }

    public function destroy(Quote $quote)
    {
        $quote->delete();

        return redirect()->route('quotes.index')->with('status', 'Quote deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = Quote::whereIn('id', $ids)->delete();

        return back()->with('status', "Deleted {$count} quote(s).");
    }

    /* ---- Helpers ------------------------------------------------------- */

    private function validateQuote(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(Quote::STATUSES)],
            'valid_until' => ['nullable', 'date'],
            'discount' => ['nullable', 'string', 'max:32'],
            'tax' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.service_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'string', 'max:32'],
        ]);
    }

    /** Rebuild the line items, computing each total in integer cents. */
    private function syncItems(Quote $quote, array $rows): void
    {
        $quote->items()->delete();

        foreach ($rows as $row) {
            $serviceId = ! empty($row['service_id']) ? (int) $row['service_id'] : null;
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '' && $serviceId) {
                $name = Product::find($serviceId)?->name ?? '';
            }

            if ($name === '') {
                continue;
            }

            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $unit = Money::parse($row['unit_price'] ?? null) ?? 0;

            $quote->items()->create([
                'service_id' => $serviceId,
                'name' => $name,
                'quantity' => $qty,
                'unit_price_cents' => $unit,
                'total_cents' => $unit * $qty,
            ]);
        }

        $quote->recalcTotals();
    }

    private function serviceOptions(): array
    {
        return Product::with('variants')->orderBy('name')->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => number_format($p->price_from_cents / 100, 2, '.', ''),
            ])
            ->values()
            ->all();
    }

    private function addressFrom(Request $request): ?array
    {
        $address = array_filter([
            'line1' => $request->input('address.line1'),
            'line2' => $request->input('address.line2'),
            'city' => $request->input('address.city'),
            'state' => $request->input('address.state'),
            'postcode' => $request->input('address.postcode'),
        ], fn ($v) => filled($v));

        return $address ?: null;
    }

    private function addressLines(?array $address): array
    {
        if (! $address) {
            return [];
        }

        $city = trim(
            ($address['city'] ?? '')
            .(! empty($address['state']) ? ', '.$address['state'] : '')
            .' '.($address['postcode'] ?? '')
        );

        return array_values(array_filter([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            $city,
        ]));
    }

    /** Email the quote to the customer, wrapped so a mail failure never blocks. */
    private function emailQuote(Quote $quote): void
    {
        $to = $quote->customer?->email;
        if (! $to) {
            return;
        }

        rescue(fn () => Mail::to($to)->send(new QuoteMail($quote)), null, false);
    }
}
