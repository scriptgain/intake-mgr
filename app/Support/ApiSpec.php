<?php

namespace App\Support;

/**
 * The single source of truth for the public API reference. Both the human docs
 * page (/docs) and the machine spec (/docs/openapi.json) are generated from
 * here, so they never drift. Base URL is this install's own APP_URL.
 */
class ApiSpec
{
    public static function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/'.config('api.version', 'v1');
    }

    public static function meta(): array
    {
        return [
            'title' => config('brand.name', 'IntakeMGR').' API',
            'version' => (string) config('api.version', 'v1'),
            'base_url' => self::baseUrl(),
            'rate_limit' => (int) config('api.rate_limit', 120),
            'per_page' => (int) config('api.per_page', 25),
            'max_per_page' => (int) config('api.max_per_page', 100),
        ];
    }

    /** Common query params shared by list endpoints. */
    private static function listParams(array $filters = []): array
    {
        return array_merge([
            ['name' => 'q', 'in' => 'query', 'desc' => 'Search term.'],
            ['name' => 'per_page', 'in' => 'query', 'desc' => 'Results per page (max '.config('api.max_per_page', 100).').'],
            ['name' => 'page', 'in' => 'query', 'desc' => 'Page number.'],
        ], $filters);
    }

    private static function f(string $name, string $desc): array
    {
        return ['name' => $name, 'in' => 'query', 'desc' => $desc];
    }

    /**
     * Every resource group. Each: label, description, fields (response body),
     * endpoints [method, path, summary, params?, body?].
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'service-requests', 'label' => 'Service Requests',
                'desc' => 'Incoming requests captured from the intake form or logged by staff.',
                'fields' => ['id', 'number', 'status (new|triaged|converted|closed)', 'priority (low|normal|high|urgent)', 'source', 'name', 'email', 'phone', 'subject', 'description', 'address', 'customer_id', 'ticket_id', 'work_order_id', 'closed_at', 'created_at', 'updated_at'],
                'endpoints' => [
                    ['GET', '/service-requests', 'List service requests', self::listParams([self::f('status', 'Filter by status.'), self::f('priority', 'Filter by priority.')])],
                    ['GET', '/service-requests/{id}', 'Retrieve a service request'],
                    ['POST', '/service-requests', 'Create a service request', [], ['name', 'email', 'phone?', 'subject', 'description?', 'service_id?', 'priority?', 'address?']],
                    ['PUT', '/service-requests/{id}', 'Update a service request'],
                    ['DELETE', '/service-requests/{id}', 'Delete a service request'],
                    ['POST', '/service-requests/{id}/convert-ticket', 'Convert into a ticket'],
                    ['POST', '/service-requests/{id}/convert-work-order', 'Convert into a work order'],
                    ['POST', '/service-requests/{id}/close', 'Close the request'],
                ],
            ],
            [
                'key' => 'tickets', 'label' => 'Tickets',
                'desc' => 'The service-desk conversation. Replies may be public or internal staff notes.',
                'fields' => ['id', 'number', 'subject', 'description', 'status (open|pending|in_progress|resolved|closed)', 'priority', 'customer_id', 'service_request_id', 'project_id', 'assigned_user_id', 'last_reply_at', 'resolved_at', 'closed_at', 'created_at', 'updated_at'],
                'endpoints' => [
                    ['GET', '/tickets', 'List tickets', self::listParams([self::f('status', 'Filter by status.'), self::f('priority', 'Filter by priority.'), self::f('assigned_user_id', 'Filter by technician.'), self::f('customer_id', 'Filter by customer.')])],
                    ['GET', '/tickets/{id}', 'Retrieve a ticket'],
                    ['POST', '/tickets', 'Create a ticket', [], ['subject', 'description?', 'customer_id?', 'priority?', 'assigned_user_id?']],
                    ['PUT', '/tickets/{id}', 'Update a ticket'],
                    ['DELETE', '/tickets/{id}', 'Delete a ticket'],
                    ['GET', '/tickets/{id}/replies', 'List replies', [self::f('include_internal', 'Include internal staff notes (1|0).')]],
                    ['POST', '/tickets/{id}/replies', 'Post a reply', [], ['body', 'is_internal?']],
                    ['POST', '/tickets/{id}/status', 'Change status', [], ['status']],
                    ['POST', '/tickets/{id}/assign', 'Assign a technician', [], ['assigned_user_id']],
                    ['POST', '/tickets/{id}/work-order', 'Spawn a work order from this ticket', [], ['title?', 'scheduled_at?']],
                ],
            ],
            [
                'key' => 'work-orders', 'label' => 'Work Orders',
                'desc' => 'Scheduled service visits. Completing one generates an invoice.',
                'fields' => ['id', 'number', 'title', 'notes', 'status (scheduled|in_progress|on_hold|completed|cancelled)', 'scheduled_at', 'duration_minutes', 'address', 'subtotal_cents', 'subtotal_formatted', 'customer_id', 'ticket_id', 'project_id', 'assigned_user_id', 'invoice_order_id', 'items[]', 'created_at', 'updated_at'],
                'endpoints' => [
                    ['GET', '/work-orders', 'List work orders', self::listParams([self::f('status', 'Filter by status.'), self::f('upcoming', 'Only upcoming (1).')])],
                    ['GET', '/work-orders/{id}', 'Retrieve a work order'],
                    ['POST', '/work-orders', 'Create a work order', [], ['customer_id?', 'title', 'scheduled_at?', 'duration_minutes?', 'assigned_user_id?', 'address?', 'items[] {service_id?, name, quantity, unit_price}']],
                    ['PUT', '/work-orders/{id}', 'Update a work order'],
                    ['DELETE', '/work-orders/{id}', 'Delete a work order'],
                    ['POST', '/work-orders/{id}/status', 'Change status', [], ['status']],
                    ['POST', '/work-orders/{id}/complete', 'Mark complete and generate an invoice'],
                    ['POST', '/work-orders/{id}/cancel', 'Cancel the work order', [], ['reason?']],
                    ['POST', '/work-orders/{id}/reschedule', 'Reschedule', [], ['scheduled_at', 'duration_minutes?']],
                ],
            ],
            [
                'key' => 'projects', 'label' => 'Projects',
                'desc' => 'Engagements that group tickets and work orders.',
                'fields' => ['id', 'number', 'name', 'description', 'status (planning|active|on_hold|completed|cancelled)', 'progress_percent', 'starts_on', 'due_on', 'customer_id', 'assigned_user_id', 'created_at', 'updated_at'],
                'endpoints' => [
                    ['GET', '/projects', 'List projects', self::listParams([self::f('status', 'Filter by status.')])],
                    ['GET', '/projects/{id}', 'Retrieve a project'],
                    ['POST', '/projects', 'Create a project', [], ['name', 'description?', 'customer_id?', 'assigned_user_id?', 'starts_on?', 'due_on?', 'status?']],
                    ['PUT', '/projects/{id}', 'Update a project'],
                    ['DELETE', '/projects/{id}', 'Delete a project'],
                    ['POST', '/projects/{id}/status', 'Change status', [], ['status']],
                ],
            ],
            [
                'key' => 'invoices', 'label' => 'Invoices',
                'desc' => 'Read-only view of invoices (the billable orders behind work orders).',
                'fields' => ['id', 'number', 'financial_status', 'currency', 'subtotal_cents', 'tax_cents', 'total_cents', 'total_formatted', 'refunded_cents', 'paid_at', 'work_order_id', 'project_id', 'customer_id', 'items[]', 'created_at'],
                'endpoints' => [
                    ['GET', '/invoices', 'List invoices', self::listParams([self::f('financial_status', 'Filter by financial status.')])],
                    ['GET', '/invoices/{id}', 'Retrieve an invoice'],
                ],
            ],
            [
                'key' => 'services', 'label' => 'Services',
                'desc' => 'Read-only catalog of the services offered.',
                'fields' => ['id', 'name', 'slug', 'description', 'status'],
                'endpoints' => [
                    ['GET', '/services', 'List services', self::listParams()],
                    ['GET', '/services/{id}', 'Retrieve a service'],
                ],
            ],
            [
                'key' => 'booking-types', 'label' => 'Booking Types',
                'desc' => 'The kinds of appointment offered, with durations and pricing.',
                'fields' => ['id', 'name', 'slug', 'description', 'duration_minutes', 'buffer_before_minutes', 'buffer_after_minutes', 'total_minutes', 'price_cents', 'price_formatted', 'assigned_user_id', 'color', 'is_active', 'position'],
                'endpoints' => [
                    ['GET', '/booking-types', 'List booking types', self::listParams([self::f('is_active', 'Filter active (1|0).')])],
                    ['GET', '/booking-types/{id}', 'Retrieve a booking type'],
                    ['POST', '/booking-types', 'Create a booking type', [], ['name', 'duration_minutes', 'price?', 'buffer_before_minutes?', 'buffer_after_minutes?', 'assigned_user_id?', 'is_active?']],
                    ['PUT', '/booking-types/{id}', 'Update a booking type'],
                    ['DELETE', '/booking-types/{id}', 'Delete a booking type'],
                ],
            ],
            [
                'key' => 'availability', 'label' => 'Availability',
                'desc' => 'A staff member\'s weekly working hours, exceptions, and timezone.',
                'fields' => ['user_id', 'timezone', 'rules[] {weekday, start_time, end_time, is_active}', 'exceptions[] {date, is_available, start_time, end_time, reason}'],
                'endpoints' => [
                    ['GET', '/users/{user}/availability', 'Get a staff member\'s availability'],
                    ['PUT', '/users/{user}/availability', 'Replace availability', [], ['timezone', 'days {0-6: {enabled, start, end}}', 'exceptions[] {date, is_available, start?, end?, reason?}']],
                ],
            ],
            [
                'key' => 'customers', 'label' => 'Customers',
                'desc' => 'The people who submit requests and pay invoices.',
                'fields' => ['id', 'name', 'email', 'phone', 'created_at'],
                'endpoints' => [
                    ['GET', '/customers', 'List customers', self::listParams()],
                    ['GET', '/customers/{id}', 'Retrieve a customer'],
                    ['POST', '/customers', 'Create a customer'],
                    ['PUT', '/customers/{id}', 'Update a customer'],
                ],
            ],
            [
                'key' => 'account', 'label' => 'Account',
                'desc' => 'The authenticated token\'s own context.',
                'fields' => ['id', 'name', 'email', 'role'],
                'endpoints' => [
                    ['GET', '/me', 'The staff account this token belongs to'],
                ],
            ],
        ];
    }
}
