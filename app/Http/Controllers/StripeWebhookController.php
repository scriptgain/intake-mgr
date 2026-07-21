<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StripeEvent;
use App\Services\Payments\OrderPayments;
use App\Services\Payments\PaymentSettings;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stripe webhook receiver.
 *
 * This is the endpoint that makes a payment durable. The browser redirect is a
 * convenience; the webhook is the guarantee. A shopper who closes the tab during
 * 3D Secure, loses signal mid-redirect, or whose browser is killed by the OS
 * still ends up with a paid order because Stripe tells us out of band.
 *
 * Four defences, in order:
 *
 *  1. SIGNATURE. Unsigned, mis-signed and tampered requests are rejected. The
 *     request body is part of the HMAC, so an attacker cannot edit the amount or
 *     the order number in a captured payload and have it still verify.
 *  2. REPLAY WINDOW. A signature older than the tolerance is refused, so a
 *     captured-and-stored request does not stay valid indefinitely.
 *  3. UNIQUE EVENT ID. Stripe redelivers on any non-2xx. The unique index on
 *     stripe_events.event_id means a redelivered payment_intent.succeeded is
 *     recognised and skipped rather than posting a second payment.
 *  4. IDEMPOTENT APPLIERS. Even if all of the above were bypassed, markPaid()
 *     takes a row lock and returns early on an already-paid order.
 *
 * RESPONSE CODES ARE DELIBERATE. A rejected request gets 400 so Stripe surfaces
 * it in the dashboard. A duplicate gets 200, because it was handled. A genuine
 * internal error gets 500 so Stripe retries. Anything we do not care about gets
 * 200, because asking Stripe to retry an event we will never act on just fills
 * their queue.
 *
 * LOGGING LEVEL IS DELIBERATE TOO. These installs run LOG_LEVEL=error, which
 * silently swallowed webhook rejection warnings in the sibling build. Rejections
 * therefore log at ERROR: a webhook being refused is either a misconfiguration
 * or an attack, and both need to be visible in a default install.
 */
class StripeWebhookController extends Controller
{
    /** Events this store acts on. Everything else is acknowledged and dropped. */
    private const HANDLED = [
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'payment_intent.canceled',
        'charge.refunded',
        'charge.dispute.created',
    ];

    public function __invoke(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = PaymentSettings::webhookSecret();

        /* ---- 1. Signature, replay window, tamper check ------------------- */

        $verified = StripeGateway::verifySignature($payload, $signature, $secret);

        if (! $verified['ok']) {
            // ERROR, not warning: must survive LOG_LEVEL=error.
            Log::error('Stripe webhook rejected', [
                'reason' => $verified['reason'],
                'ip' => $request->ip(),
                'has_signature' => $signature !== null,
                // Never the payload. Its length is enough to tell a probe from
                // a real event without recording anyone's billing details.
                'payload_bytes' => strlen($payload),
            ]);

            // A flat 400 with no detail. Telling the caller WHY verification
            // failed hands them a tuning signal for the next attempt.
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || empty($event['id']) || empty($event['type'])) {
            Log::error('Stripe webhook rejected', ['reason' => 'unparseable_body', 'ip' => $request->ip()]);

            return response()->json(['error' => 'invalid payload'], 400);
        }

        /* ---- 2. Mode check ----------------------------------------------- */

        // A live event arriving at a store running test keys (or the reverse)
        // means the wrong endpoint secret is wired up. Acting on it would post
        // real money onto a test order or vice versa.
        $eventLive = (bool) ($event['livemode'] ?? false);

        if ($eventLive === PaymentSettings::isTestMode()) {
            Log::error('Stripe webhook rejected', [
                'reason' => 'livemode_mismatch',
                'event_livemode' => $eventLive,
                'store_mode' => PaymentSettings::mode(),
                'type' => $event['type'],
            ]);

            return response()->json(['error' => 'mode mismatch'], 400);
        }

        /* ---- 3. Replay / redelivery guard -------------------------------- */

        // Insert-and-catch rather than check-then-insert: two workers handed the
        // same redelivery at the same instant both reach this line, and the
        // unique index decides which one proceeds.
        try {
            $record = StripeEvent::create([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'livemode' => $eventLive,
                'status' => 'received',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (! $this->isDuplicateKey($e)) {
                throw $e;
            }

            Log::info('Stripe webhook redelivery ignored', [
                'event_id' => $event['id'],
                'type' => $event['type'],
            ]);

            // 200: this event WAS handled, on its first delivery. Returning an
            // error would make Stripe retry it forever.
            return response()->json(['status' => 'duplicate'], 200);
        }

        /* ---- 4. Dispatch -------------------------------------------------- */

        if (! in_array($event['type'], self::HANDLED, true)) {
            $record->markIgnored('unhandled_type');

            return response()->json(['status' => 'ignored'], 200);
        }

        try {
            $this->handle($event, $record);
        } catch (\Throwable $e) {
            $record->markFailed(class_basename($e));

            Log::error('Stripe webhook handler failed', [
                'event_id' => $event['id'],
                'type' => $event['type'],
                'exception' => class_basename($e),
                'message' => OrderPayments::redact($e->getMessage()),
            ]);

            // 500 so Stripe retries. The event row is already written, so the
            // retry will be seen as a duplicate; that is a deliberate trade.
            // Losing a payment notification is worse than a stuck retry, and the
            // failed row is visible for a human to reconcile.
            return response()->json(['error' => 'handler failed'], 500);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function handle(array $event, StripeEvent $record): void
    {
        $object = data_get($event, 'data.object', []);

        $order = $this->resolveOrder($event['type'], $object);

        if (! $order) {
            $record->markIgnored('order_not_found');

            return;
        }

        $record->forceFill(['order_id' => $order->id])->save();

        match ($event['type']) {
            'payment_intent.succeeded' => $this->handleSucceeded($order, $object, $record),
            'payment_intent.payment_failed' => $this->handleFailed($order, $object, $record),
            'payment_intent.canceled' => $this->handleCanceled($order, $record),
            'charge.refunded' => $this->handleRefunded($order, $object, $record),
            'charge.dispute.created' => $this->handleDispute($order, $object, $record),
            default => $record->markIgnored('unhandled_type'),
        };
    }

    /**
     * Find the order this event is about.
     *
     * Matched on our OWN stored intent id first, then on the metadata we set
     * when creating the intent. Never on an amount, and never on an email: both
     * are attacker-controllable in a forged payload, and matching on them would
     * let a crafted event settle an unrelated order.
     */
    private function resolveOrder(string $type, array $object): ?Order
    {
        $intentId = str_starts_with($type, 'charge.')
            ? ($object['payment_intent'] ?? null)
            : ($object['id'] ?? null);

        if ($intentId && $order = Order::where('stripe_payment_intent_id', $intentId)->first()) {
            return $order;
        }

        // Fallback for the narrow window where Stripe's webhook beats our own
        // write of stripe_payment_intent_id. The metadata was set by us on the
        // create call, so it is not shopper-supplied.
        if ($orderId = data_get($object, 'metadata.order_id')) {
            return Order::find($orderId);
        }

        if ($number = data_get($object, 'metadata.order_number')) {
            return Order::where('number', $number)->first();
        }

        return null;
    }

    private function handleSucceeded(Order $order, array $intent, StripeEvent $record): void
    {
        /*
         * AMOUNT CHECK. The webhook's amount is never used as the amount, but it
         * IS compared against what the order says it should be. A mismatch means
         * either a bug or someone paying a different total than we recorded, and
         * either way a human needs to look. It is recorded rather than acted on,
         * because refusing to mark a genuinely-paid order as paid would be worse.
         */
        $received = (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0);

        if ($received !== (int) $order->total_cents) {
            Log::error('Stripe payment amount does not match order total', [
                'order' => $order->number,
                'order_total_cents' => (int) $order->total_cents,
                'received_cents' => $received,
            ]);

            $order->recordEvent('payment_mismatch', 'Payment Amount Did Not Match Order Total', [
                'order_total_cents' => (int) $order->total_cents,
                'received_cents' => $received,
            ], null);
        }

        /*
         * The webhook payload's latest_charge is an id string, not the expanded
         * object, so the card brand and last four are not in it. Re-fetch the
         * intent expanded so the order gets its card label. If that call fails
         * the payment is still applied: brand/last4 are cosmetic, the payment is
         * not.
         */
        $expanded = StripeGateway::retrievePaymentIntent($intent['id'] ?? '', ['latest_charge']);

        OrderPayments::markPaid($order, $expanded['ok'] ? $expanded['data'] : $intent);

        $record->markProcessed('marked_paid');
    }

    private function handleFailed(Order $order, array $intent, StripeEvent $record): void
    {
        OrderPayments::markFailed(
            $order,
            data_get($intent, 'last_payment_error.message', 'The card was declined.')
        );

        $record->markProcessed('marked_failed');
    }

    private function handleCanceled(Order $order, StripeEvent $record): void
    {
        OrderPayments::markVoided($order);

        $record->markProcessed('marked_voided');
    }

    /**
     * A refund issued from the Stripe dashboard rather than from our admin.
     *
     * Converges on Stripe's number rather than incrementing ours: the charge
     * object carries the authoritative total refunded, so setting it is
     * idempotent where adding to it would double-count on a redelivery that
     * somehow slipped past the event-id guard.
     */
    private function handleRefunded(Order $order, array $charge, StripeEvent $record): void
    {
        $refundedTotal = (int) ($charge['amount_refunded'] ?? 0);

        if ($refundedTotal <= (int) $order->refunded_cents) {
            $record->markIgnored('already_recorded');

            return;
        }

        $delta = $refundedTotal - (int) $order->refunded_cents;

        DB::transaction(function () use ($order, $refundedTotal) {
            /** @var Order $locked */
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

            $locked->forceFill([
                'refunded_cents' => $refundedTotal,
                'financial_status' => $refundedTotal >= (int) $locked->total_cents
                    ? 'refunded'
                    : 'partially_refunded',
                'refunded_at' => now(),
            ])->save();
        });

        $order->fresh()->recordEvent(
            'refunded',
            'Refunded '.\App\Support\Money::format($delta).' In Stripe',
            ['amount' => $delta, 'refunded_total' => $refundedTotal, 'source' => 'stripe_dashboard'],
            null
        );

        rescue(fn () => $order->fresh()->customer?->refreshTotals(), null, false);

        $record->markProcessed('refund_synced');
    }

    private function handleDispute(Order $order, array $dispute, StripeEvent $record): void
    {
        // Recorded, not acted on. Auto-cancelling or auto-restocking on a
        // dispute would let a fraudulent chargeback drive inventory.
        $order->recordEvent('dispute', 'Chargeback Opened', [
            'amount' => (int) ($dispute['amount'] ?? 0),
            'reason' => $dispute['reason'] ?? null,
            'status' => $dispute['status'] ?? null,
        ], null);

        Log::error('Stripe chargeback opened', [
            'order' => $order->number,
            'reason' => $dispute['reason'] ?? null,
        ]);

        $record->markProcessed('dispute_recorded');
    }

    /** Portable duplicate-key detection across MySQL (1062) and SQLite. */
    private function isDuplicateKey(\Illuminate\Database\QueryException $e): bool
    {
        return in_array((string) ($e->errorInfo[1] ?? ''), ['1062', '19'], true)
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
