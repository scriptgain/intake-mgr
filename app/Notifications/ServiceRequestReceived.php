<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a "Request Service" intake form is submitted.
 *
 * One class, two audiences: the requester gets a "we got it" acknowledgement,
 * the store gets an alert with a deep link into the admin. The audience is
 * passed in rather than inferred so the same notification can be routed to an
 * anonymous store address and to the customer's own mailbox.
 */
class ServiceRequestReceived extends Notification
{
    use Queueable;

    public function __construct(
        public ServiceRequest $serviceRequest,
        public string $audience = 'customer',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->audience === 'staff'
            ? $this->staffMail()
            : $this->customerMail();
    }

    private function customerMail(): MailMessage
    {
        $store = config('shop.store_name');

        return (new MailMessage)
            ->subject('We Received Your Request '.$this->serviceRequest->number)
            ->greeting('Hello '.$this->serviceRequest->name.',')
            ->line('Thanks for reaching out to '.$store.'. We have received your service request and our team will review it shortly.')
            ->line('Reference: '.$this->serviceRequest->number)
            ->line('Subject: '.$this->serviceRequest->subject)
            ->line('We will be in touch soon to confirm the details and next steps.');
    }

    private function staffMail(): MailMessage
    {
        $sr = $this->serviceRequest;

        return (new MailMessage)
            ->subject('New Service Request '.$sr->number.': '.$sr->subject)
            ->greeting('New Service Request')
            ->line($sr->name.' ('.$sr->email.') submitted a new request.')
            ->line('Subject: '.$sr->subject)
            ->when($sr->phone, fn (MailMessage $m) => $m->line('Phone: '.$sr->phone))
            ->action('Open In Admin', route('service-requests.show', $sr))
            ->line('Reference: '.$sr->number);
    }
}
