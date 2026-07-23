<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Services\ServiceDesk\QuoteActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Customer-facing quotes. Two ways in:
 *   - the client area (auth:customer), scoped to the signed-in customer;
 *   - a public accept_token link, so an emailed quote can be answered without
 *     an account. Both funnel accept/decline through QuoteActions.
 */
class QuoteController extends Controller
{
    /* ---- Client area (auth:customer) ---------------------------------- */

    public function index()
    {
        return view('shop.account.quotes', [
            'quotes' => auth('customer')->user()->quotes()->paginate(15),
        ]);
    }

    public function show(Quote $quote)
    {
        abort_unless($quote->customer_id === auth('customer')->id(), 404);

        $quote->load(['items', 'invoice']);

        return view('shop.account.quote', [
            'quote' => $quote,
            'payUrl' => $this->invoicePayUrl($quote),
        ]);
    }

    public function accept(Quote $quote)
    {
        abort_unless($quote->customer_id === auth('customer')->id(), 404);

        if (! $quote->is_actionable) {
            return back()->with('warning', 'This quote can no longer be accepted.');
        }

        QuoteActions::accept($quote, auth('customer')->user()->name);

        return back()->with('status', 'Thank you. Your quote has been accepted.');
    }

    public function decline(Request $request, Quote $quote)
    {
        abort_unless($quote->customer_id === auth('customer')->id(), 404);

        if (! $quote->is_actionable) {
            return back()->with('warning', 'This quote can no longer be declined.');
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        QuoteActions::decline($quote, $data['reason'] ?? null, auth('customer')->user()->name);

        return back()->with('status', 'Your quote has been declined.');
    }

    /* ---- Public token link (no auth) ---------------------------------- */

    public function publicShow(string $token)
    {
        $quote = $this->resolveToken($token);
        $quote->load(['items']);

        return view('shop.quote-public', ['quote' => $quote]);
    }

    public function publicAccept(string $token)
    {
        $quote = $this->resolveToken($token);

        if (! $quote->is_actionable) {
            return redirect()->route('shop.quote.public', $token)
                ->with('warning', 'This quote can no longer be accepted.');
        }

        QuoteActions::accept($quote, $quote->customer?->name ?? 'Customer');

        return redirect()->route('shop.quote.public', $token)
            ->with('status', 'Thank you. Your quote has been accepted.');
    }

    public function publicDecline(Request $request, string $token)
    {
        $quote = $this->resolveToken($token);

        if (! $quote->is_actionable) {
            return redirect()->route('shop.quote.public', $token)
                ->with('warning', 'This quote can no longer be declined.');
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        QuoteActions::decline($quote, $data['reason'] ?? null, $quote->customer?->name ?? 'Customer');

        return redirect()->route('shop.quote.public', $token)
            ->with('status', 'Your quote has been declined.');
    }

    /* ---- Helpers ------------------------------------------------------- */

    private function resolveToken(string $token): Quote
    {
        return Quote::where('accept_token', $token)->firstOrFail();
    }

    /** Signed Pay link for the quote's generated invoice, when it is unpaid. */
    private function invoicePayUrl(Quote $quote): ?string
    {
        $invoice = $quote->invoice;

        if (! $invoice || $invoice->is_paid || $invoice->is_cancelled || (int) $invoice->total_cents <= 0) {
            return null;
        }

        return URL::temporarySignedRoute('shop.checkout.payment', now()->addDay(), ['order' => $invoice->number]);
    }
}
