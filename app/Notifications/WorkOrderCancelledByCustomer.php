<?php

namespace App\Notifications;

use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Staff alert: a customer cancelled a scheduled work order from the portal. */
class WorkOrderCancelledByCustomer extends Notification
{
    use Queueable;

    public function __construct(
        public WorkOrder $workOrder,
        public string $customerName,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $wo = $this->workOrder;

        return (new MailMessage)
            ->subject('Cancelled By Customer: Work Order '.$wo->number)
            ->greeting('Work Order Cancelled')
            ->line($this->customerName.' cancelled work order '.$wo->number.' ('.$wo->title.').')
            ->when($wo->scheduled_at, fn (MailMessage $m) => $m->line('Was scheduled: '.$wo->scheduled_at->format('F j, Y g:i A')))
            ->when($this->reason, fn (MailMessage $m) => $m->line('Reason: '.$this->reason))
            ->action('Open Work Order', route('work-orders.show', $wo))
            ->line('No further action is required unless you want to follow up.');
    }
}
