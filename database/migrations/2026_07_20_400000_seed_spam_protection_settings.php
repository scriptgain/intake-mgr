<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed spam-protection defaults into the settings table.
 *
 * Idempotent: every row is written with insertOrIgnore against the unique `key`
 * index, so a value a merchant has already changed is left untouched and the
 * migration is safe to re-run. No new table is created; spam settings live in
 * the shared settings store like every other DB-driven config value.
 *
 * SAFE OUT-OF-THE-BOX DEFAULT: provider = built-in challenge (needs no external
 * keys) with the honeypot + time-trap baseline on, so protection works with zero
 * configuration. The official PUBLIC TEST keys for reCAPTCHA v2, hCaptcha and
 * Turnstile are seeded too, so an admin can switch to any of those and demo it
 * immediately; they are clearly test keys and must be swapped for real ones
 * before go-live. reCAPTCHA v3 has no official test keys, so it is left blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $now = now();

        $defaults = [
            // Active provider + baseline.
            'captcha_provider' => 'builtin',
            'captcha_honeypot_enabled' => '1',
            'captcha_min_seconds' => '2',

            // Fail policy: closed everywhere by default; contact fails open.
            'captcha_fail_policy' => 'closed',
            'captcha_contact_fail_open' => '1',

            // reCAPTCHA v3 score gate.
            'captcha_recaptcha_v3_threshold' => '0.5',

            // Per-surface toggles. Checkout OFF by default (it can cost orders).
            'captcha_on_admin_login' => '1',
            'captcha_on_account_login' => '1',
            'captcha_on_account_register' => '1',
            'captcha_on_contact' => '1',
            'captcha_on_checkout' => '0',

            // ---- Official PUBLIC TEST keys (clearly labelled; swap before go-live) ----
            // Google reCAPTCHA v2 test pair (always passes).
            'captcha_recaptcha_v2_site_key' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
            'captcha_recaptcha_v2_secret_key' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',

            // reCAPTCHA v3: no official test keys exist. Left blank on purpose.
            'captcha_recaptcha_v3_site_key' => '',
            'captcha_recaptcha_v3_secret_key' => '',

            // hCaptcha test pair (always passes).
            'captcha_hcaptcha_site_key' => '10000000-ffff-ffff-ffff-000000000001',
            'captcha_hcaptcha_secret_key' => '0x0000000000000000000000000000000000000000',

            // Cloudflare Turnstile test pair (always passes, invisible).
            'captcha_turnstile_site_key' => '1x00000000000000000000AA',
            'captcha_turnstile_secret_key' => '1x0000000000000000000000000000000AA',
        ];

        $rows = [];
        foreach ($defaults as $key => $value) {
            $rows[] = [
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // insertOrIgnore skips any key that already exists, preserving edits.
        DB::table('settings')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', [
            'captcha_provider',
            'captcha_honeypot_enabled',
            'captcha_min_seconds',
            'captcha_fail_policy',
            'captcha_contact_fail_open',
            'captcha_recaptcha_v3_threshold',
            'captcha_on_admin_login',
            'captcha_on_account_login',
            'captcha_on_account_register',
            'captcha_on_contact',
            'captcha_on_checkout',
            'captcha_recaptcha_v2_site_key',
            'captcha_recaptcha_v2_secret_key',
            'captcha_recaptcha_v3_site_key',
            'captcha_recaptcha_v3_secret_key',
            'captcha_hcaptcha_site_key',
            'captcha_hcaptcha_secret_key',
            'captcha_turnstile_site_key',
            'captcha_turnstile_secret_key',
        ])->delete();
    }
};
