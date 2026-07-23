<?php

namespace App\View\Components\Layouts;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Merchant admin shell.
 *
 * The navigation tree, the derived left menu, the breadcrumb trail and the
 * avatar initials all used to be computed in @php blocks at the top of the
 * Blade file. They live here instead so the template is markup only.
 */
class App extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $nav;

    /** @var array<int, array>|null */
    public ?array $activeGroupItems;

    /** @var array<int, array{label: string, href: ?string}> */
    public array $crumbs;

    public string $initials;

    public bool $isDashboard;

    public function __construct(
        public ?string $title = null,
        public ?string $maxWidth = null,
    ) {
        $this->maxWidth ??= config('shop.max_width', 'max-w-7xl');

        $user = auth()->user();

        $this->initials = Str::of($user?->name ?? 'Admin')
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        $this->nav = $this->navigation();
        $this->activeGroupItems = $this->activeGroupItems();
        $this->crumbs = $this->breadcrumbs();
        $this->isDashboard = request()->routeIs('dashboard');
    }

    /**
     * Top-level navigation. Grouped the way a merchant's day divides rather
     * than the way the database does: what you sell, what sold, and the rules
     * that govern selling.
     */
    private function navigation(): array
    {
        return [
            [
                'type' => 'link',
                'label' => 'Dashboard',
                'href' => route('dashboard'),
                'icon' => 'dashboard',
                'active' => request()->routeIs('dashboard'),
            ],
            [
                'type' => 'group',
                'label' => 'Service Desk',
                'icon' => 'bolt',
                'active' => request()->routeIs('service-requests.*', 'tickets.*', 'quotes.*', 'work-orders.*', 'projects.*'),
                'items' => [
                    ['Intake Inbox', route('service-requests.index'), 'envelope', request()->routeIs('service-requests.*')],
                    ['Tickets', route('tickets.index'), 'bell', request()->routeIs('tickets.*')],
                    ['Quotes', route('quotes.index'), 'document', request()->routeIs('quotes.*')],
                    ['Work Orders', route('work-orders.index'), 'truck', request()->routeIs('work-orders.*')],
                    ['Projects', route('projects.index'), 'folder', request()->routeIs('projects.*')],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Scheduling',
                'icon' => 'clock',
                'active' => request()->routeIs('booking-types.*', 'availability.*', 'calendar.*'),
                'items' => [
                    ['Booking Types', route('booking-types.index'), 'clock', request()->routeIs('booking-types.*')],
                    ['Availability', route('availability.index'), 'users', request()->routeIs('availability.*')],
                    ['My Calendar', route('calendar.index'), 'refresh', request()->routeIs('calendar.*')],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Billing',
                'icon' => 'credit-card',
                'active' => request()->routeIs('orders.*', 'customers.*', 'discounts.*'),
                'items' => [
                    ['Invoices', route('orders.index'), 'credit-card', request()->routeIs('orders.*')],
                    ['Customers', route('customers.index'), 'users', request()->routeIs('customers.*')],
                    ['Discounts', route('discounts.index'), 'tag', request()->routeIs('discounts.*')],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Catalog',
                'icon' => 'bag',
                'active' => request()->routeIs('products.*', 'collections.*', 'seo.*'),
                'items' => [
                    ['Services', route('products.index'), 'bag', request()->routeIs('products.*')],
                    ['Collections', route('collections.index'), 'folder', request()->routeIs('collections.*')],
                    ['SEO Health', route('seo.index'), 'globe', request()->routeIs('seo.*')],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Content',
                'icon' => 'book',
                'active' => request()->routeIs('help-categories.*', 'help-articles.*', 'store-pages.*'),
                'items' => [
                    ['Help Categories', route('help-categories.index'), 'folder', request()->routeIs('help-categories.*')],
                    ['Help Articles', route('help-articles.index'), 'book', request()->routeIs('help-articles.*')],
                    ['Policy Pages', route('store-pages.index'), 'info', request()->routeIs('store-pages.*')],
                ],
            ],
            // Themes, Templates, Shipping, Tax and the rest live under the
            // Settings hub (the gear menu), keeping the primary nav to the
            // day-to-day work areas.
        ];
    }

    /**
     * When the current route sits inside a top-nav group, expose that group's
     * items so the shell can render a left menu for it (same pattern as
     * settings).
     */
    private function activeGroupItems(): ?array
    {
        foreach ($this->nav as $item) {
            if (($item['type'] ?? '') === 'group' && ($item['active'] ?? false)) {
                return $item['items'];
            }
        }

        return null;
    }

    private function breadcrumbs(): array
    {
        $routeName = Route::currentRouteName() ?? '';
        $section = strtok($routeName, '.');

        $map = [
            'products' => ['Products', 'products.index'],
            'collections' => ['Collections', 'collections.index'],
            'seo' => ['SEO Health', 'seo.index'],
            'orders' => ['Invoices', 'orders.index'],
            'customers' => ['Customers', 'customers.index'],
            'discounts' => ['Discounts', 'discounts.index'],
            'service-requests' => ['Intake Inbox', 'service-requests.index'],
            'tickets' => ['Tickets', 'tickets.index'],
            'quotes' => ['Quotes', 'quotes.index'],
            'work-orders' => ['Work Orders', 'work-orders.index'],
            'projects' => ['Projects', 'projects.index'],
            'booking-types' => ['Booking Types', 'booking-types.index'],
            'availability' => ['Availability', 'availability.index'],
            'calendar' => ['My Calendar', 'calendar.index'],
            'themes' => ['Themes', 'themes.index'],
            'templates' => ['Templates', 'templates.index'],
            'help-categories' => ['Help Categories', 'help-categories.index'],
            'help-articles' => ['Help Articles', 'help-articles.index'],
            'store-pages' => ['Policy Pages', 'store-pages.index'],
            'shipping' => ['Shipping', 'shipping.index'],
            'taxes' => ['Tax', 'taxes.index'],
            'settings' => ['Settings', 'settings.index'],
        ];

        if (! isset($map[$section])) {
            return [];
        }

        [$label, $indexRoute] = $map[$section];
        $isIndex = $routeName === $indexRoute;

        $crumbs = [['label' => $label, 'href' => $isIndex ? null : route($indexRoute)]];

        if (! $isIndex && $this->title && $this->title !== $label) {
            $crumbs[] = ['label' => $this->title, 'href' => null];
        }

        return $crumbs;
    }

    public function render()
    {
        return view('components.layouts.app');
    }
}
