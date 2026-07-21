/**
 * ShopMGR storefront JS. Registers Alpine components used across the public
 * shop views: the product variant picker, quantity steppers, and the
 * checkout live-quote form. Loaded with `defer` after the Alpine CDN script,
 * so `alpine:init` always fires after this file has registered everything.
 */
document.addEventListener('alpine:init', () => {
    /**
     * Product variant picker. Keys into the server-built `variantMap`
     * (combo string, e.g. "Medium|Charcoal", or "default" for a product with
     * no options) to drive the displayed price, stock state, and the hidden
     * `variant_id` field — no pricing/stock logic is computed here, it only
     * looks up what the controller already prepared.
     */
    Alpine.data('variantPicker', (config) => ({
        axes: config.axes || [],
        variantMap: config.variantMap || {},
        selections: {},
        variantId: null,
        price: '',
        compareAt: null,
        sku: null,
        inStock: true,
        lowStock: false,
        qtyLeft: 0,

        init() {
            const initial = config.initial || [];
            this.axes.forEach((axis, i) => {
                if (initial[i] !== undefined) {
                    this.selections[axis.index] = initial[i];
                }
            });
            this.refresh();
        },

        key() {
            if (!this.axes.length) return 'default';
            return this.axes.map((axis) => this.selections[axis.index]).join('|');
        },

        current() {
            return this.variantMap[this.key()] || null;
        },

        select(axisIndex, value) {
            this.selections[axisIndex] = value;
            this.refresh();
        },

        refresh() {
            const variant = this.current();
            if (variant) {
                this.variantId = variant.id;
                this.price = variant.price;
                this.compareAt = variant.compare_at;
                this.sku = variant.sku;
                this.inStock = variant.in_stock;
                this.lowStock = variant.low_stock;
                this.qtyLeft = variant.qty;
            } else {
                this.variantId = null;
                this.price = '—';
                this.compareAt = null;
                this.sku = null;
                this.inStock = false;
                this.lowStock = false;
                this.qtyLeft = 0;
            }
        },

        isSelected(axisIndex, value) {
            return this.selections[axisIndex] === value;
        },

        /**
         * A pill is available when some variant exists with this value at
         * this axis position AND matches every other axis the shopper has
         * already picked (unselected axes are wildcards).
         */
        isAvailable(axisIndex, value) {
            const position = this.axes.findIndex((axis) => axis.index === axisIndex);
            if (position === -1) return true;

            return Object.keys(this.variantMap).some((key) => {
                const parts = key.split('|');
                if (parts[position] !== value) return false;

                return this.axes.every((axis, i) => {
                    if (i === position) return true;
                    const selected = this.selections[axis.index];
                    return selected === undefined || parts[i] === selected;
                });
            });
        },
    }));

    /** Small +/- quantity control shared by the product page and cart lines. */
    Alpine.data('quantityStepper', (initial = 1) => ({
        qty: initial,
        inc() {
            this.qty = Math.min(999, (parseInt(this.qty, 10) || 1) + 1);
        },
        dec() {
            this.qty = Math.max(1, (parseInt(this.qty, 10) || 1) - 1);
        },
    }));

    /**
     * Checkout live re-quote. Posts the shipping address + chosen rate to
     * shop.checkout.quote and swaps in whatever PricingService (via the
     * controller) computed — this file never adds up money itself.
     */
    Alpine.data('checkoutForm', (config) => ({
        address: config.address || { country: 'US', state: '', postcode: '' },
        totals: config.totals || {},
        discountError: config.discountError || null,
        selectedRateId: config.selectedRateId ?? null,
        needsShipping: !!config.needsShipping,
        billingSame: true,
        ratesLoaded: false,
        rates: [],
        quoting: false,

        quote() {
            this.quoting = true;

            fetch(config.quoteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    shipping_address: this.address,
                    shipping_rate_id: this.selectedRateId,
                }),
            })
                .then((response) => response.json())
                .then((data) => {
                    this.totals = data.totals;
                    this.rates = data.rates;
                    this.selectedRateId = data.selected_rate_id;
                    this.discountError = data.discount_error;
                    this.ratesLoaded = true;
                })
                .catch(() => {
                    // Network hiccup: leave the last known totals/rates in place
                    // rather than blanking a working quote.
                })
                .finally(() => {
                    this.quoting = false;
                });
        },
    }));

    /**
     * Stripe Payment Element.
     *
     * Registered here, in the SAME file that already registers variantPicker and
     * checkoutForm, precisely because this file is loaded BEFORE the Alpine CDN
     * in the shop layout. Alpine fires alpine:init the moment it starts; a
     * separate JS file added after the Alpine tag would register nothing and the
     * card form would silently render empty. Do not move this into its own file
     * without moving its <script> tag above the Alpine one too.
     *
     * Nothing about the amount lives here. The client secret was minted server
     * side against the order's stored total, so this component cannot influence
     * what is charged even if the page is tampered with in the browser.
     */
    Alpine.data('stripePayment', (config) => ({
        stripe: null,
        elements: null,
        ready: false,
        processing: false,
        error: config.initialError || null,

        async init() {
            if (!config.publishableKey || !config.clientSecret) {
                this.error = this.error || 'Card payments are unavailable right now.';
                return;
            }

            try {
                const Stripe = await loadStripeJs();
                this.stripe = Stripe(config.publishableKey);

                // Match the storefront rather than shipping Stripe's default
                // blue: the brand accent is passed in from the server.
                this.elements = this.stripe.elements({
                    clientSecret: config.clientSecret,
                    appearance: {
                        theme: 'stripe',
                        variables: {
                            colorPrimary: config.accent || '#e11d48',
                            colorDanger: '#e11d48',
                            borderRadius: '8px',
                            fontFamily: 'ui-sans-serif, system-ui, sans-serif',
                        },
                    },
                });

                const payment = this.elements.create('payment', {
                    layout: 'tabs',
                    // Billing details are already collected by checkout; asking
                    // again inside the card widget is duplicate data entry.
                    fields: { billingDetails: { address: 'auto' } },
                });

                payment.mount(this.$refs.paymentElement);
                payment.on('ready', () => { this.ready = true; });
                payment.on('loaderror', () => {
                    this.error = 'The card form could not be loaded. Please refresh and try again.';
                });
            } catch (e) {
                this.error = 'The card form could not be loaded. Please refresh and try again.';
            }
        },

        async pay() {
            // Client-side double-submit guard. It is a courtesy, not a control:
            // the real guard is the order's Stripe idempotency key server side.
            if (this.processing || !this.ready) {
                return;
            }

            this.processing = true;
            this.error = null;

            try {
                const submitted = await this.elements.submit();
                if (submitted.error) {
                    this.error = messageFor(submitted.error);
                    this.processing = false;
                    return;
                }

                // redirect: 'if_required' handles both shapes of 3D Secure. A
                // modal challenge resolves inline and returns here; a challenge
                // that needs the issuer's own page redirects away to return_url
                // and never comes back to this line.
                const result = await this.stripe.confirmPayment({
                    elements: this.elements,
                    clientSecret: config.clientSecret,
                    confirmParams: { return_url: config.returnUrl },
                    redirect: 'if_required',
                });

                if (result.error) {
                    this.error = messageFor(result.error);
                    this.processing = false;
                    return;
                }

                // Settled without a redirect. Go to the server's return handler
                // anyway: it is the only thing that marks the order paid, and it
                // re-reads the outcome from Stripe rather than trusting this.
                window.location.assign(config.returnUrl);
            } catch (e) {
                this.error = 'Something went wrong while confirming your payment. Your card has not been charged.';
                this.processing = false;
            }
        },
    }));
});

/**
 * Load Stripe.js on demand and resolve with the global constructor.
 *
 * Loaded lazily rather than from the shared layout so the 100kb of Stripe.js is
 * only fetched on the one page that needs it, and so no ordering relationship
 * with the Alpine CDN has to be maintained: this resolves whenever it resolves,
 * long after alpine:init has fired.
 */
let stripeJsPromise = null;

function loadStripeJs() {
    if (window.Stripe) {
        return Promise.resolve(window.Stripe);
    }

    if (stripeJsPromise) {
        return stripeJsPromise;
    }

    stripeJsPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.async = true;
        script.onload = () => (window.Stripe ? resolve(window.Stripe) : reject(new Error('stripe_unavailable')));
        script.onerror = () => reject(new Error('stripe_load_failed'));
        document.head.appendChild(script);
    });

    return stripeJsPromise;
}

/**
 * Decide what a shopper is allowed to read from a Stripe error.
 *
 * A card error or a validation error is repeated verbatim: "your card was
 * declined", "your card number is incomplete" are exactly what the shopper needs
 * and hiding them makes a fixable problem look like a broken checkout.
 *
 * Everything else (api_error, invalid_request_error, authentication_error) is
 * replaced. Those carry configuration detail, can quote back a key fragment, and
 * tell the shopper nothing they can act on. This mirrors the same decision made
 * server side in OrderPayments::shopperMessage.
 */
function messageFor(error) {
    if (error && (error.type === 'card_error' || error.type === 'validation_error') && error.message) {
        return error.message;
    }

    return 'We could not take that payment right now. Please check your details or try another card. Your card has not been charged.';
}
