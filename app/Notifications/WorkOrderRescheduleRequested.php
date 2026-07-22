<?php

namespace App\Notifications;

use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Staff alert: a customer asked to move a scheduled work order. This is a
 * request, not a change; the work order's time is untouched until staff confirm.
 */
class WorkOrderRescheduleRequested extends Notification
{
    use Queueable;

    public function __construct(
        public WorkOrder $workOrder,
        public Carbon $preferredAt,
        public string $customerName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $wo = $this->workOrder;

        return (new MailMessage)
            ->subject('Reschedule Requested: Work Order '.$wo->number)
            ->greeting('Reschedule Request')
            ->line($this->customerName.' requested a new time for work order '.$wo->number.' ('.$wo->title.').')
            ->line('Requested time: '.$this->preferredAt->format('F j, Y g:i A'))
            ->when($wo->scheduled_at, fn (MailMessage $m) => $m->line('Currently scheduled: '.$wo->scheduled_at->format('F j, Y g:i A')))
            ->action('Open Work Order', route('work-orders.show', $wo))
            ->line('Confirm or propose a new time from the admin.');
    }
}
