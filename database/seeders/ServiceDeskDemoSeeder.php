<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Database\Seeder;

/**
 * Populates the service desk with believable demo data so the admin panel and
 * the customer portal are not empty on the sandbox. Idempotent: it seeds a
 * customer only if that customer has no tickets yet, so re-running is safe.
 */
class ServiceDeskDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedServices();

        $staff = User::where('role', 'admin')->first() ?? User::first();

        // Attach to whichever demo customers exist. The rich dataset goes to the
        // first customer (so their portal is populated), a lighter one to the
        // second. Prefer the classic persona emails, fall back to any customers.
        $customers = Customer::orderBy('id')->take(2)->get();
        $primary = Customer::where('email', 'ada@example.com')->first() ?? $customers->get(0);
        $secondary = Customer::where('email', 'theo@example.com')->first() ?? $customers->get(1);

        if ($primary && $primary->tickets()->count() === 0) {
            $this->seedForAda($primary, $staff);
        }

        if ($secondary && $secondary->id !== $primary?->id && $secondary->tickets()->count() === 0) {
            $this->seedForTheo($secondary, $staff);
        }
    }

    /** A small catalog of billable services. */
    private function seedServices(): void
    {
        $services = [
            ['Pool Cleaning & Chemical Balance', 'Weekly pool service: skim, vacuum, brush, and balance chemicals.', 12000],
            ['HVAC Tune-Up', 'Full inspection and tune-up of a residential heating and cooling system.', 14900],
            ['Drain Clearing', 'Clear a single blocked drain, including a camera inspection.', 18500],
            ['Water Heater Repair', 'Diagnose and repair a residential water heater.', 22500],
            ['Seasonal Maintenance Visit', 'A scheduled preventative maintenance visit.', 9900],
        ];

        foreach ($services as [$name, $desc, $priceCents]) {
            $slug = Product::uniqueSlug($name);
            Product::firstOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($name)],
                [
                    'name' => $name,
                    'excerpt' => $desc,
                    'description' => $desc,
                    'status' => 'active',
                    'product_type' => 'service',
                    'requires_shipping' => false,
                ]
            );
        }
    }

    private function seedForAda(Customer $c, ?User $staff): void
    {
        $now = now();

        // A converted request -> ticket -> completed work order -> paid invoice,
        // grouped under a project.
        $project = Project::create([
            'customer_id' => $c->id,
            'assigned_user_id' => $staff?->id,
            'name' => 'Backyard Pool Restoration',
            'description' => 'Full restoration and ongoing maintenance of the backyard pool.',
            'status' => 'active',
            'starts_on' => $now->copy()->subDays(20)->toDateString(),
            'due_on' => $now->copy()->addDays(20)->toDateString(),
        ]);
        $project->recordActivity('created', 'Project created', [], $staff?->id);

        $req = ServiceRequest::create([
            'customer_id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => '(602) 555-0142',
            'subject' => 'Green pool needs a full clean',
            'description' => 'The pool has turned green after the storm and needs a full clean and chemical reset.',
            'status' => 'converted',
            'priority' => 'high',
            'source' => 'web',
        ]);
        $req->recordActivity('created', 'Request submitted', [], null, $c->name);

        $ticket = Ticket::create([
            'customer_id' => $c->id,
            'service_request_id' => $req->id,
            'project_id' => $project->id,
            'assigned_user_id' => $staff?->id,
            'subject' => 'Green pool needs a full clean',
            'description' => $req->description,
            'status' => 'resolved',
            'priority' => 'high',
            'last_reply_at' => $now->copy()->subDays(4),
            'last_reply_by' => 'staff',
            'resolved_at' => $now->copy()->subDays(3),
        ]);
        $req->forceFill(['ticket_id' => $ticket->id])->save();
        $ticket->recordActivity('created', 'Ticket opened from request '.$req->number, [], $staff?->id);
        TicketReply::create(['ticket_id' => $ticket->id, 'customer_id' => $c->id, 'author_type' => 'customer', 'author_name' => $c->name, 'body' => 'Please come as soon as you can, we have guests this weekend.', 'is_internal' => false, 'created_at' => $now->copy()->subDays(5)]);
        TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $staff?->id, 'author_type' => 'staff', 'author_name' => $staff?->name, 'body' => 'Booked you in for Thursday morning. We will do a full clean and reset the chemistry.', 'is_internal' => false, 'created_at' => $now->copy()->subDays(4)]);
        $ticket->recordActivity('status', 'Marked resolved', [], $staff?->id);

        $completed = WorkOrder::create([
            'customer_id' => $c->id,
            'ticket_id' => $ticket->id,
            'project_id' => $project->id,
            'assigned_user_id' => $staff?->id,
            'title' => 'Full pool clean and chemical reset',
            'status' => 'completed',
            'scheduled_at' => $now->copy()->subDays(3)->setTime(9, 0),
            'started_at' => $now->copy()->subDays(3)->setTime(9, 5),
            'completed_at' => $now->copy()->subDays(3)->setTime(11, 30),
        ]);
        WorkOrderItem::create(['work_order_id' => $completed->id, 'name' => 'Pool Cleaning & Chemical Balance', 'quantity' => 1, 'unit_price_cents' => 12000, 'total_cents' => 12000]);
        WorkOrderItem::create(['work_order_id' => $completed->id, 'name' => 'Emergency Algae Treatment', 'quantity' => 1, 'unit_price_cents' => 6500, 'total_cents' => 6500]);
        $completed->recalcTotals();
        $completed->recordActivity('completed', 'Work completed', [], $staff?->id);

        // Paid invoice for the completed work order.
        $invoice = Order::create([
            'number' => Order::nextNumber(),
            'customer_id' => $c->id,
            'email' => $c->email,
            'financial_status' => 'paid',
            'status' => 'open',
            'currency' => 'USD',
            'subtotal_cents' => $completed->subtotal_cents,
            'total_cents' => $completed->subtotal_cents,
            'payment_gateway' => 'stripe',
            'payment_provider' => 'stripe',
            'card_brand' => 'visa',
            'card_last4' => '4242',
            'paid_at' => $now->copy()->subDays(3)->setTime(12, 0),
            'work_order_id' => $completed->id,
            'project_id' => $project->id,
        ]);
        foreach ($completed->items as $item) {
            OrderItem::create(['order_id' => $invoice->id, 'name' => $item->name, 'quantity' => $item->quantity, 'unit_price_cents' => $item->unit_price_cents, 'total_cents' => $item->total_cents, 'requires_shipping' => false]);
        }
        $completed->forceFill(['invoice_order_id' => $invoice->id])->save();

        // An upcoming scheduled work order with an unpaid invoice due after it.
        $upcoming = WorkOrder::create([
            'customer_id' => $c->id,
            'project_id' => $project->id,
            'assigned_user_id' => $staff?->id,
            'title' => 'Weekly maintenance visit',
            'status' => 'scheduled',
            'scheduled_at' => $now->copy()->addDays(4)->setTime(10, 0),
        ]);
        WorkOrderItem::create(['work_order_id' => $upcoming->id, 'name' => 'Seasonal Maintenance Visit', 'quantity' => 1, 'unit_price_cents' => 9900, 'total_cents' => 9900]);
        $upcoming->recalcTotals();
        $upcoming->recordActivity('scheduled', 'Scheduled for '.$upcoming->scheduled_at->format('M j, g:i A'), [], $staff?->id);

        // A separate open ticket, not yet converted.
        $open = Ticket::create([
            'customer_id' => $c->id,
            'assigned_user_id' => $staff?->id,
            'subject' => 'Pool heater not turning on',
            'description' => 'The heater does not fire up when I set it above 80.',
            'status' => 'in_progress',
            'priority' => 'normal',
            'last_reply_at' => $now->copy()->subDay(),
            'last_reply_by' => 'customer',
        ]);
        $open->recordActivity('created', 'Ticket opened', [], null, $c->name);
        TicketReply::create(['ticket_id' => $open->id, 'customer_id' => $c->id, 'author_type' => 'customer', 'author_name' => $c->name, 'body' => 'It clicks but never lights. Can someone take a look?', 'is_internal' => false, 'created_at' => $now->copy()->subDay()]);
    }

    private function seedForTheo(Customer $c, ?User $staff): void
    {
        $now = now();

        $req = ServiceRequest::create([
            'customer_id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'subject' => 'AC blowing warm air',
            'description' => 'The upstairs AC is blowing warm air even on the coldest setting.',
            'status' => 'new',
            'priority' => 'urgent',
            'source' => 'web',
        ]);
        $req->recordActivity('created', 'Request submitted', [], null, $c->name);

        $wo = WorkOrder::create([
            'customer_id' => $c->id,
            'assigned_user_id' => $staff?->id,
            'title' => 'HVAC diagnostic and tune-up',
            'status' => 'in_progress',
            'scheduled_at' => $now->copy()->setTime(14, 0),
            'started_at' => $now->copy()->setTime(14, 10),
        ]);
        WorkOrderItem::create(['work_order_id' => $wo->id, 'name' => 'HVAC Tune-Up', 'quantity' => 1, 'unit_price_cents' => 14900, 'total_cents' => 14900]);
        $wo->recalcTotals();
        $wo->recordActivity('scheduled', 'Technician on site', [], $staff?->id);
    }
}
