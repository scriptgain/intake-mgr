<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\LoginAttempt;
use Illuminate\Console\Command;

/**
 * Nightly catalog housekeeping. Keeps the tables that grow without bound from
 * growing without bound.
 */
class Housekeeping extends Command
{
    protected $signature = 'shop:housekeeping';

    protected $description = 'Prune expired carts, old audit entries, and stale login attempts.';

    public function handle(): int
    {
        // Abandoned carts that were never converted and have aged out. Converted
        // carts are kept — they are the paper trail behind an order.
        $carts = Cart::whereNull('converted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
        $this->info("Pruned {$carts} expired carts.");

        $auditDays = (int) config('shop.audit_log_days', 180);
        if ($auditDays > 0) {
            $audits = AuditLog::where('created_at', '<', now()->subDays($auditDays))->delete();
            $this->info("Pruned {$audits} audit entries older than {$auditDays} days.");
        }

        $attempts = LoginAttempt::where('created_at', '<', now()->subDays(30))->delete();
        $this->info("Pruned {$attempts} login attempts older than 30 days.");

        return self::SUCCESS;
    }
}
