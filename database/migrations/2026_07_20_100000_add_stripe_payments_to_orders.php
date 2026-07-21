<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Card payment state on the order, plus the webhook event ledger.
 *
 * Only the non-sensitive residue of a card is ever stored: brand and last four.
 * No PAN, no CVC, no token, no full Stripe payload. Everything else that matters
 * lives at Stripe and is fetched by id when needed.
 *
 * Guarded with hasTable/hasColumn throughout so re-running against an install
 * that already has some of this is a no-op rather than an error.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'stripe_payment_intent_id')) {
                    $table->string('stripe_payment_intent_id')->nullable()->index();
                }
                if (! Schema::hasColumn('orders', 'stripe_charge_id')) {
                    $table->string('stripe_charge_id')->nullable();
                }
                // The only card details ShopMGR ever persists.
                if (! Schema::hasColumn('orders', 'card_brand')) {
                    $table->string('card_brand', 32)->nullable();
                }
                if (! Schema::hasColumn('orders', 'card_last4')) {
                    $table->string('card_last4', 4)->nullable();
                }
                // Minted once per order and reused for every intent-create
                // attempt, so a double submit collapses to one charge at Stripe.
                if (! Schema::hasColumn('orders', 'payment_idempotency_key')) {
                    $table->string('payment_idempotency_key', 64)->nullable()->unique();
                }
                // Whether this order's charge was real money. Test orders must
                // be distinguishable forever, not just while the switch is set.
                if (! Schema::hasColumn('orders', 'livemode')) {
                    $table->boolean('livemode')->default(false);
                }
                // Staff-facing reason. Already redacted of anything key-shaped.
                if (! Schema::hasColumn('orders', 'payment_failure_reason')) {
                    $table->string('payment_failure_reason', 255)->nullable();
                }
                if (! Schema::hasColumn('orders', 'refunded_at')) {
                    $table->timestamp('refunded_at')->nullable();
                }
                // Set once the confirmation mail actually went out, so a resend
                // is a deliberate act and a retry cannot spam the customer.
                if (! Schema::hasColumn('orders', 'confirmation_sent_at')) {
                    $table->timestamp('confirmation_sent_at')->nullable();
                }
            });
        }

        /*
         * Webhook event ledger.
         *
         * The unique index on event_id is the replay guard: Stripe redelivers on
         * any non-2xx, and a redelivered payment_intent.succeeded must not be
         * able to post a second payment. The insert races the duplicate rather
         * than checking-then-inserting, so two workers receiving the same
         * redelivery simultaneously still only process it once.
         *
         * The payload itself is NOT stored. Only the id, the type, and what we
         * decided to do about it.
         */
        if (! Schema::hasTable('stripe_events')) {
            Schema::create('stripe_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_id')->unique(); // evt_...
                $table->string('type');               // payment_intent.succeeded
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('livemode')->default(false);
                // received | processed | ignored | failed
                $table->string('status')->default('received');
                $table->string('note', 255)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['type', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');

        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'stripe_payment_intent_id', 'stripe_charge_id', 'card_brand', 'card_last4',
                'payment_idempotency_key', 'livemode', 'payment_failure_reason',
                'refunded_at', 'confirmation_sent_at',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
