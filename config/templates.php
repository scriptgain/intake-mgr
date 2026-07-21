<?php

/*
|--------------------------------------------------------------------------
| Editable templates
|--------------------------------------------------------------------------
|
| The allowlist behind Appearance -> Templates. Merchants edit real Blade, so
| this list is a curated surface rather than "every file in resources/views":
| it groups templates the way a merchant thinks about their shop, and it keeps
| framework partials (pagination, mail scaffolding) out of reach where an edit
| would buy nothing but risk.
|
| Each entry: view name => [label, description, risk].
|
| risk:
|   normal   - a mistake costs you a page
|   high     - a mistake costs you ORDERS or a customer receipt, so the UI
|              says so loudly before the merchant starts typing
|
*/

return [

    'groups' => [

        'storefront' => [
            'label' => 'Storefront',
            'icon' => 'bag',
            'description' => 'The pages customers browse.',
            'views' => [
                'components.layouts.shop' => ['Storefront Layout', 'Header, navigation, footer, and the page shell around every shop page.', 'high'],
                'shop.home' => ['Home Page', 'The shop landing page: hero, featured products, collections.', 'normal'],
                'shop.catalog' => ['Catalog / Search Results', 'The all-products grid, its filters, and search results.', 'normal'],
                'shop.collections' => ['Collections Index', 'The list of collections.', 'normal'],
                'shop.product' => ['Product Page', 'Gallery, buy box, variant picker, and description.', 'high'],
                'components.product-card' => ['Product Card', 'The repeated product tile used across grids.', 'normal'],
            ],
        ],

        'checkout' => [
            'label' => 'Cart & Checkout',
            'icon' => 'credit-card',
            'description' => 'The pages that take money. Edit with care.',
            'views' => [
                'shop.cart' => ['Cart', 'Line items, quantity controls, and the path to checkout.', 'high'],
                'shop.checkout' => ['Checkout', 'Contact, address, shipping method, and order review.', 'high'],
                'shop.payment' => ['Payment', 'The payment step and its gateway fields.', 'high'],
                'shop.confirmation' => ['Order Confirmation', 'The thank-you page shown after a successful order.', 'high'],
            ],
        ],

        'account' => [
            'label' => 'Customer Account',
            'icon' => 'user',
            'description' => 'Signed-in customer pages.',
            'views' => [
                'shop.account.index' => ['Account Overview', 'The customer dashboard.', 'normal'],
                'shop.account.order' => ['Customer Order Detail', 'A single past order as the customer sees it.', 'normal'],
                'shop.account.profile' => ['Account Profile', 'Name, email, and password form.', 'normal'],
                'shop.account.addresses' => ['Address Book', 'Saved shipping and billing addresses.', 'normal'],
                'shop.account.login' => ['Customer Sign In', 'The storefront login form.', 'normal'],
                'shop.account.register' => ['Customer Registration', 'The storefront account creation form.', 'normal'],
            ],
        ],

        'emails' => [
            'label' => 'Emails',
            'icon' => 'envelope',
            'description' => 'Transactional mail. A broken template means a customer never gets their receipt.',
            'views' => [
                'emails.orders.confirmation' => ['Order Confirmation Email', 'The receipt sent to the customer when an order is placed.', 'high'],
                'emails.orders.merchant' => ['New Order Email', 'The notification sent to your staff when an order comes in.', 'high'],
            ],
        ],

        'admin' => [
            'label' => 'Admin',
            'icon' => 'dashboard',
            'description' => 'Screens only your staff see.',
            'views' => [
                'admin.dashboard' => ['Admin Dashboard', 'The merchant dashboard: revenue, worklist, recent orders.', 'normal'],
            ],
        ],

    ],

    /*
    | How long a live preview stays active before it expires on its own.
    | A preview that outlives the merchant's attention is a support ticket, so
    | it times out rather than waiting to be switched off.
    */
    'preview_minutes' => 20,

];
