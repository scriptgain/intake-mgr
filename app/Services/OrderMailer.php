<?php

namespace App\Services;

use App\Mail\OrderConfirmation;
use App\Mail\OrderPlacedNotification;
use App\Models\Order;
use App\Services\Payments\PaymentSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The one place order mail is sent from.
 *
 * Two mails per order: a confirmation to the customer and a notification to the
 * merchant. Both are sent once, gated on the order's confirmation_sent_at stamp,
 * because the callers are not single-shot. markPaid() can be reached by the
 * browser redirect and by the webhook, and a webhook that Stripe redelivers must
 * not re-mail a customer who already has their receipt.
 *
 * Every send is wrapped. Mail is a side effect of a payment that has already
 * succeeded: an SMTP outage must not bubble an exception into a confirmation
 * page, and must not turn a webhook into a non-2xx that Stripe then retries
 * forever.
 */
class OrderMailer
{
    /** Send after a card payment settles. */
    public static function sendForPaidOrder(Order $order): void
    {
        self::send($order);
    }

    /**
     * Send after a manual/offline order is placed. The customer still needs
     * their confirmation and their payment instructions even though no money has
     * moved yet.
     */
    public static function sendForPlacedOrder(Order $order): void
    {
        self::send($order);
    }

    /**
     * Resend on purpose, from the admin. Ignores the once-only stamp because a
     * staff member clicking "Resend Confirmation" means it.
     */
    public static function resend(Order $order): bool
    {
        return self::deliverCustomer($order->fresh(['items', 'customer']));
    }

    private static function send(Order $order): void
    {
        $order = $order->fresh(['items', 'customer']);

        if (! $order || $order->confirmation_sent_at) {
            return;
        }

        // Stamp BEFORE sending, not after. If the mailer is slow and a second
        // caller arrives mid-send, the stamp is what stops the duplicate; a
        // stamp written afterwards would leave that window open.
        $order->forceFill(['confirmation_sent_at' => now()])->save();

        $customerOk = self::deliverCustomer($order);
        $merchantOk = self::deliverMerchant($order);

        if ($customerOk || $merchantOk) {
            $order->recordEvent('email', 'Order Emails Sent', array_filter([
                'customer' => $customerOk ? $order->email : null,
                'merchant' => $merchantOk ? PaymentSettings::merchantNotifyEmail() : null,
            ]), null);
        }
    }

    private static function deliverCustomer(Order $order): bool
    {
        if (! $order->email || ! PaymentSettings::sendCustomerEmails()) {
            return false;
        }

        try {
            Mail::to($order->email)->send(new OrderConfirmation($order));

            return true;
        } catch (\Throwable $e) {
            // Log that it failed and for which order. Never the recipient's
            // details, never the message body.
            Log::warning('Order confirmation email failed', [
                'order' => $order->number,
                'exception' => class_basename($e),
            ]);

            return false;
        }
    }

    private static function deliverMerchant(Order $order): bool
    {
        $to = PaymentSettings::merchantNotifyEmail();

        if (! $to || ! PaymentSettings::sendMerchantEmails()) {
            return false;
        }

        try {
            Mail::to($to)->send(new OrderPlacedNotification($order));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Merchant order notification failed', [
                'order' => $order->number,
                'exception' => class_basename($e),
            ]);

            return false;
        }
    }
}
