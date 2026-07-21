<?php

namespace App\Providers;

use App\Services\TemplateOverrideResolver;
use App\Services\ThemeService;
use App\View\DatabaseViewFinder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Wires up the two merchant-facing customisation systems:
 * the Template Manager (database Blade overrides) and the Theme Manager.
 *
 * The view finder is swapped in register() rather than boot(). Every provider's
 * register() runs before any provider's boot(), and AppServiceProvider::boot()
 * registers view composers, which resolves the view factory - and the factory
 * captures whatever finder exists at that moment. Swapping later would leave
 * the factory holding the stock finder and overrides would silently do nothing.
 * boot() re-asserts it defensively for the case where something resolves the
 * factory even earlier.
 */
class CustomizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Both carry per-request state (resolved paths, the active theme), so
        // they must be one instance per request.
        $this->app->singleton(TemplateOverrideResolver::class);
        $this->app->singleton(ThemeService::class);

        $this->app->extend('view.finder', function ($finder, $app) {
            if ($finder instanceof DatabaseViewFinder) {
                return $finder;
            }

            $replacement = new DatabaseViewFinder(
                $app['files'],
                $finder->getPaths(),
                $finder->getExtensions()
            );

            foreach ($finder->getHints() as $namespace => $paths) {
                $replacement->addNamespace($namespace, $paths);
            }

            return $replacement;
        });
    }

    public function boot(): void
    {
        try {
            $factory = View::getFacadeRoot();

            if (! $factory->getFinder() instanceof DatabaseViewFinder) {
                $factory->setFinder($this->app->make('view.finder'));
            }
        } catch (\Throwable $e) {
            // Never let the customisation layer stop the app booting.
        }
    }
}
