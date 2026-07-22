<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StripeEvent;
use App\Services\Payments\AuthorizeNetPaymentGateway;
use App\Services\Payments\OrderPayments;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Authorize.Net webhook receiver.
 *
 * Structurally identical to StripeWebhookController: the browser response after
 * the synchronous Accept.js charge is the convenience, this endpoint is the
 * durability guarantee. A shopper whose browser dies between the charge posting
 * and our response still ends up with a paid order because Authorize.Net tells us
 * out of band, and a refund issued from the merchant's Authorize.Net dashboard
 * (rather than our admin) converges here.
 *
 * Defences, in order:
 *
 *  1. SIGNATURE. HMAC-SHA512 of the raw body keyed by the Signature Key. The body
 *     is part of the HMAC, so an attacker cannot edit the amount or the invoice
 *     number in a captured payload and have it still verify. Verified through the
 *     gateway; a failure is a flat 400 with no detail.
 *  2. UNIQUE NOTIFICATION ID. Authorize.Net redelivers on any non-2xx. The unique
 *     index on stripe_events.event_id (reused, provider='authorizenet') means a
 *     redelivered notification is recognised and skipped.
 *  3. IDEMPOTENT APPLIERS. Even past the above, recordExternalPayment takes a row
 *     lock and returns early on an already-paid order, and the refund path
 *     recognises a refund we already recorded.
 *
 * RESPONSE CODES ARE DELIBERATE, mirroring the Stripe receiver: rejected -> 400
 * (visible in the merchant's Authorize.Net dashboard), duplicate -> 200 (it was
 * handled), internal error -> 500 (so it retries), ignored -> 200 (retrying an
 * event we never act on just fills their queue).
 *
 * LOGGING LEVEL. These installs run LOG_LEVEL=error. Rejections log at ERROR so a
 * misconfiguration or an attack is visible in a default install. Never a secret,
 * never the payload body.
 */
class AuthorizeNetWebhookController extends Controller
{
    /** Events this store acts on. Everything else is acknowledged and dropped. */
    private const HANDLED = [
        'net.authorize.payment.authcapture.created',
        'net.authorize.payment.refund.created',
    ];

    public function __invoke(Request $request)
    {
        $payload = $request->getContent();

        /* ---- 1. Signature + tamper check -------------------------------- */

        $verified = app(AuthorizeNetPaymentGateway::class)
            ->verifyWebhook($payload, $request->headers->all());

        if (! $verified['ok']) {
            // ERROR, not warning: must survive LOG_LEVEL=error.
            Log::error('Authorize.Net webhook rejected', [
                'reason' => $verified['reason'],
                'ip' => $request->ip(),
                // Never the payload. Its length distinguishes a probe from a real
                // event without recording anyone's billing details.
                'payload_bytes' => strlen($payload),
            ]);

            // Flat 400, no detail: telling the caller WHY hands them a tuning
            // signal for the next attempt.
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $event = $verified['event'];

        $notificationId = $event['notificationId'] ?? null;
        $type = $event['eventType'] ?? null;

        if (! is_array($event) || empty($notificationId) || empty($type)) {
            Log::error('Authorize.Net webhook rejected', ['reason' => 'unparseable_body', 'ip' => $request->ip()]);

            return response()->json(['error' => 'invalid payload'], 400);
        }

        /* ---- 2. Replay / redelivery guard ------------------------------- */

        // Insert-and-catch rather than check-then-insert: two workers handed the
        // same redelivery at the same instant both reach this line, and the
        // unique index on event_id decides which one proceeds.
        try {
            $record = StripeEvent::create([
                'event_id' => $notificationId,
                'type' => $type,
                'provider' => 'authorizenet',
                'livemode' => false,
                'status' => 'received',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (! $this->isDuplicateKey($e)) {
                throw $e;
            }

            Log::info('Authorize.Net webhook redelivery ignored', [
                'notification_id' => $notificationId,
                'type' => $type,
            ]);

            // 200: handled on first delivery. An error would make it retry forever.
            return response()->json(['status' => 'duplicate'], 200);
        }

        /* ---- 3. Dispatch ------------------------------------------------- */

        if (! in_array($type, self::HANDLED, true)) {
            $record->markIgnored('unhandled_type');

            return response()->json(['status' => 'ignored'], 200);
        }

        try {
            $this->handle($type, $event, $record);
        } catch (\Throwable $e) {
            $record->markFailed(class_basename($e));

            Log::error('Authorize.Net webhook handler failed', [
                'notification_id' => $notificationId,
                'type' => $type,
                'exception' => class_basename($e),
                'message' => OrderPayments::redact($e->getMessage()),
            ]);

            // 500 so Authorize.Net retries. The event row is already written, so
            // the retry is seen as a duplicate: a deliberate trade. Losing a
            // payment notification is worse than a stuck retry, and the failed
            // row is visible for a human to reconcile.
            return response()->json(['error' => 'handler failed'], 500);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function handle(string $type, array $event, StripeEvent $record): void
    {
        $body = data_get($event, 'payload', []);

        $order = $this->resolveOrder($body);

        if (! $order) {
            $record->markIgnored('order_not_found');

            return;
        }

        $record->forceFill(['order_id' => $order->id])->save();

        match ($type) {
            'net.authorize.payment.authcapture.created' => $this->handleAuthCapture($order, $body, $record),
            'net.authorize.payment.refund.created' => $this->handleRefund($order, $body, $record),
            default => $record->markIgnored('unhandled_type'),
        };
    }

    /**
     * Find the order this notification is about.
     *
     * Matched on our OWN stored transaction id first, then on the invoice number
     * we set on the charge (which is the order number). Never on an amount: it is
     * attacker-controllable in a forged payload, and the signature already proves
     * the body is authentic, but resolving by amount would still let a replayed
     * body settle an unrelated order.
     *
     * For an authcapture the payload id IS the charge id, so the first match
     * wins. For a refund the payload id is the refund's own id (a different
     * transaction), so resolution falls through to the invoice number.
     */
    private function resolveOrder(array $body): ?Order
    {
        if ($transId = ($body['id'] ?? null)) {
            if ($order = Order::where('authnet_transaction_id', $transId)->first()) {
                return $order;
            }
        }

        if ($invoice = ($body['invoiceNumber'] ?? null)) {
            return Order::where('number', $invoice)->first();
        }

        return null;
    }

    private function handleAuthCapture(Order $order, array $body, StripeEvent $record): void
    {
        if ($order->is_paid) {
            $record->markIgnored('already_paid');

            return;
        }

        /*
         * AMOUNT CHECK. The webhook's amount is never used as the amount, but it
         * IS compared against what the order says it should be. A mismatch means
         * a bug or someone paying a different total than we recorded, and either
         * way a human needs to look. Recorded rather than acted on, because
         * refusing to mark a genuinely-paid order paid would be worse.
         */
        $received = $this->toCents($body['authAmount'] ?? null);

        if ($received !== null && $received !== (int) $order->total_cents) {
            Log::error('Authorize.Net payment amount does not match order total', [
                'order' => $order->number,
                'order_total_cents' => (int) $order->total_cents,
                'received_cents' => $received,
            ]);

            $order->recordEvent('payment_mismatch', 'Payment Amount Did Not Match Order Total', [
                'order_total_cents' => (int) $order->total_cents,
                'received_cents' => $received,
            ], null);
        }

        OrderPayments::recordExternalPayment(
            $order,
            'authorizenet',
            (string) ($body['id'] ?? $order->authnet_transaction_id),
            [],
            true // A webhook is only delivered against a live processing account.
        );

        $record->markProcessed('marked_paid');
    }

    /**
     * A refund seen on the webhook stream.
     *
     * Converges the order's refunded state. A refund we issued from our own admin
     * already incremented refunded_cents AND emits this webhook, so we skip a
     * refund whose Authorize.Net id we already recorded on the order. Only a
     * refund we have never seen (typically issued from the merchant's Authorize.Net
     * dashboard) increments here, bounded to what is still refundable so a
     * replayed body can never drive refunded_cents past the order total.
     */
    private function handleRefund(Order $order, array $body, StripeEvent $record): void
    {
        $refundTransId = (string) ($body['id'] ?? '');

        if ($refundTransId !== '' && $this->refundAlreadyRecorded($order, $refundTransId)) {
            $record->markIgnored('refund_already_recorded');

            return;
        }

        $amount = $this->toCents($body['authAmount'] ?? null);
        $refundable = max(0, (int) $order->total_cents - (int) $order->refunded_cents);
        $amount = $amount === null ? $refundable : min($amount, $refundable);

        if ($amount < 1) {
            $record->markIgnored('nothing_refundable');

            return;
        }

        DB::transaction(function () use ($order, $amount) {
            /** @var Order $locked */
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

            $refunded = (int) $locked->refunded_cents + $amount;

            $locked->forceFill([
                'refunded_cents' => $refunded,
                'financial_status' => $refunded >= (int) $locked->total_cents ? 'refunded' : 'partially_refunded',
                'refunded_at' => now(),
            ])->save();
        });

        $fresh = $order->fresh();

        $fresh->recordEvent('refunded', 'Refunded '.Money::format($amount).' In Authorize.Net', array_filter([
            'amount' => $amount,
            'refunded_total' => $fresh->refunded_cents,
            'source' => 'authnet_webhook',
            'authnet_refund_id' => $refundTransId ?: null,
        ], fn ($v) => $v !== null), null);

        rescue(fn () => $fresh->customer?->refreshTotals(), null, false);

        $record->markProcessed('refund_synced');
    }

    /** Have we already applied a refund with this Authorize.Net transaction id? */
    private function refundAlreadyRecorded(Order $order, string $refundTransId): bool
    {
        foreach ($order->events()->where('type', 'refunded')->get() as $event) {
            if ((string) data_get($event->meta, 'authnet_refund_id') === $refundTransId) {
                return true;
            }
        }

        return false;
    }

    /** Decimal-dollar string/float from the payload to integer cents, or null. */
    private function toCents($amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        // Round on the scaled value: (float) 5.10 * 100 is 509.9999 without it.
        return (int) round(((float) $amount) * 100);
    }

    /** Portable duplicate-key detection across MySQL (1062) and SQLite (19). */
    private function isDuplicateKey(\Illuminate\Database\QueryException $e): bool
    {
        return in_array((string) ($e->errorInfo[1] ?? ''), ['1062', '19'], true)
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
