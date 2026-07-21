<?php

namespace App\View;

use App\Services\TemplateOverrideResolver;
use Illuminate\View\FileViewFinder;

/**
 * The custom Blade view source behind the Template Manager.
 *
 * Laravel resolves every view name through the view finder, so replacing the
 * finder is the single narrowest place to make database overrides win over the
 * files a ScriptGain release ships. Every path into a view goes through here:
 * view(), @include, @extends, <x-component>, Mailable views. Nothing has to
 * know the override layer exists.
 *
 * Falls straight through to the normal FileViewFinder whenever there is no
 * override, so an install with zero customisation behaves exactly as shipped.
 */
class DatabaseViewFinder extends FileViewFinder
{
    /**
     * @param  string  $name
     * @return string
     */
    public function find($name)
    {
        // Namespaced views (package::view) are framework/vendor territory and
        // are deliberately not overridable.
        if (! str_contains($name, static::HINT_PATH_DELIMITER)) {
            try {
                $path = app(TemplateOverrideResolver::class)->pathFor($name);

                if ($path !== null) {
                    return $path;
                }
            } catch (\Throwable $e) {
                // An override layer that throws must never stop a page
                // rendering. Fall back to the shipped template.
            }
        }

        return parent::find($name);
    }
}
