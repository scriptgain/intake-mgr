<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * The storefront password-reset email.
 *
 * Mirrors the framework's ResetPassword notification but builds a link to the
 * shop route (shop.account.reset) instead of the staff 'password.reset' route,
 * and reads the expiry from the 'customers' broker so the copy stays truthful
 * if that window is retuned.
 */
class CustomerPasswordReset extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('shop.account.reset', ['token' => $this->token]).'?email='.urlencode($notifiable->getEmailForPasswordReset());

        $minutes = Config::get('auth.passwords.customers.expire', 60);
        $store = config('shop.store_name');

        return (new MailMessage)
            ->subject('Reset Your '.$store.' Password')
            ->greeting('Hello,')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in '.$minutes.' minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
