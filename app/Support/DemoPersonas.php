<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * One-click demo persona logins, gated to an allowlisted IP.
 *
 * This is the same trust model as the developer quick login in AuthController:
 * the buttons render only from the allowlisted IP AND every endpoint re-checks
 * the IP server-side, so a POST from anywhere else 404s. It is a convenience for
 * walking a seeded store, never a security boundary.
 *
 * The personas resolve to records created by DemoOrdersSeeder. A persona whose
 * record is missing (seeder not run) simply drops out of the picker and its
 * endpoint 404s, so a half-seeded box degrades quietly rather than erroring.
 */
class DemoPersonas
{
    /**
     * Customer personas, in display order. Each maps to a real Customer with
     * seeded order history, so signing in lands on a populated account page.
     *
     * @return array<string, array{email:string, label:string, note:string}>
     */
    public static function customerDefinitions(): array
    {
        return [
            'ada' => ['email' => 'ada@example.com', 'label' => 'Ada Chen', 'note' => '5 Orders'],
            'marcus' => ['email' => 'marcus.reed@example.com', 'label' => 'Marcus Reed', 'note' => '2 Orders'],
            'nadia' => ['email' => 'nadia@example.com', 'label' => 'Nadia Brooks', 'note' => 'Has A Refund'],
            'theo' => ['email' => 'theo@example.com', 'label' => 'Theo Alvarez', 'note' => 'Order In Progress'],
        ];
    }

    /**
     * Staff personas for the merchant admin.
     *
     * @return array<string, array{email:string, label:string, note:string}>
     */
    public static function staffDefinitions(): array
    {
        return [
            'admin' => ['email' => 'demo-admin@example.com', 'label' => 'Merchant Admin', 'note' => 'Full Access'],
            'staff' => ['email' => 'demo-staff@example.com', 'label' => 'Staff', 'note' => 'Limited Role'],
        ];
    }

    /**
     * True when the request comes from an allowlisted IP. Reuses the existing
     * dev_login_ip setting so the whole demo picker is switched on and off from
     * one place in the admin, without a deploy.
     */
    public static function allowed(Request $request): bool
    {
        $allowed = trim((string) Setting::get('dev_login_ip', ''));

        if ($allowed === '') {
            return false;
        }

        $ips = array_filter(array_map('trim', explode(',', $allowed)));

        return in_array((string) $request->ip(), $ips, true);
    }

    /**
     * The customer personas whose records actually exist, each with its resolved
     * model. Empty unless the request IP is allowlisted.
     *
     * @return array<int, array{key:string, label:string, note:string, model:Customer}>
     */
    public static function customersFor(Request $request): array
    {
        if (! self::allowed($request)) {
            return [];
        }

        $out = [];
        foreach (self::customerDefinitions() as $key => $def) {
            if ($model = Customer::where('email', $def['email'])->first()) {
                $out[] = ['key' => $key, 'label' => $def['label'], 'note' => $def['note'], 'model' => $model];
            }
        }

        return $out;
    }

    /**
     * The staff personas whose records exist, each with its resolved model.
     *
     * @return array<int, array{key:string, label:string, note:string, model:User}>
     */
    public static function staffFor(Request $request): array
    {
        if (! self::allowed($request)) {
            return [];
        }

        $out = [];
        foreach (self::staffDefinitions() as $key => $def) {
            if ($model = User::where('email', $def['email'])->first()) {
                $out[] = ['key' => $key, 'label' => $def['label'], 'note' => $def['note'], 'model' => $model];
            }
        }

        return $out;
    }

    /** Resolve a customer persona key to its record, or null. */
    public static function resolveCustomer(string $key): ?Customer
    {
        $email = self::customerDefinitions()[$key]['email'] ?? null;

        return $email ? Customer::where('email', $email)->first() : null;
    }

    /** Resolve a staff persona key to its record, or null. */
    public static function resolveStaff(string $key): ?User
    {
        $email = self::staffDefinitions()[$key]['email'] ?? null;

        return $email ? User::where('email', $email)->first() : null;
    }
}
