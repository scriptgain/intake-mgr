<?php

namespace App\View\Components;

use App\Models\WorkOrder;
use Illuminate\View\Component;

/**
 * "Add to Calendar" menu for a scheduled work order. Builds the Google and
 * Outlook web quick-add URLs here (not in the view) plus takes the ICS download
 * URL for Apple/Outlook desktop. Renders nothing if the work order is unscheduled.
 */
class AddToCalendar extends Component
{
    public bool $scheduled;

    public string $googleUrl = '';

    public string $outlookUrl = '';

    public function __construct(
        public WorkOrder $workOrder,
        public string $icsUrl,
    ) {
        $this->scheduled = (bool) $workOrder->scheduled_at;

        if ($this->scheduled) {
            $start = $workOrder->scheduled_at->copy()->utc();
            $end = $start->copy()->addMinutes((int) ($workOrder->duration_minutes ?: 60));
            $title = trim($workOrder->number.' '.($workOrder->title ?: 'Service Visit'));
            $details = trim(($workOrder->customer?->name ? 'Customer: '.$workOrder->customer->name."\n" : '').(string) $workOrder->notes);
            $location = $this->location($workOrder);

            $this->googleUrl = 'https://calendar.google.com/calendar/render?'.http_build_query([
                'action' => 'TEMPLATE',
                'text' => $title,
                'dates' => $start->format('Ymd\THis\Z').'/'.$end->format('Ymd\THis\Z'),
                'details' => $details,
                'location' => $location,
            ]);

            $this->outlookUrl = 'https://outlook.office.com/calendar/0/deeplink/compose?'.http_build_query([
                'path' => '/calendar/action/compose',
                'rru' => 'addevent',
                'subject' => $title,
                'startdt' => $start->toIso8601String(),
                'enddt' => $end->toIso8601String(),
                'body' => $details,
                'location' => $location,
            ]);
        }
    }

    private function location(WorkOrder $workOrder): string
    {
        $a = $workOrder->address;
        if (! is_array($a)) {
            return '';
        }
        $parts = array_filter([
            $a['line1'] ?? null, $a['line2'] ?? null, $a['city'] ?? null,
            trim(($a['state'] ?? '').' '.($a['postcode'] ?? '')),
        ], fn ($p) => filled($p));

        return implode(', ', $parts);
    }

    public function render()
    {
        return view('components.add-to-calendar');
    }
}
