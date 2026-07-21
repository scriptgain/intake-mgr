<?php

namespace App\Console\Commands;

use App\Services\ThemeService;
use Illuminate\Console\Command;

/**
 * Create the shipped theme presets if they are missing, and make sure exactly
 * one theme is active.
 *
 * Idempotent, so the release process can call it on every update: an existing
 * preset the merchant has since edited is left exactly as they left it.
 */
class ThemesInstall extends Command
{
    protected $signature = 'shop:themes-install';

    protected $description = 'Install the shipped storefront theme presets (idempotent).';

    public function handle(ThemeService $themes): int
    {
        $themes->ensurePresets();

        $this->info('Theme presets installed.');

        foreach (\App\Models\Theme::orderBy('id')->get() as $theme) {
            $this->line(sprintf(
                '  %-18s %s%s',
                $theme->slug,
                $theme->name,
                $theme->is_active ? '  [active]' : ''
            ));
        }

        return self::SUCCESS;
    }
}
