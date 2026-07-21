<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TemplateOverride;
use App\Models\TemplateOverrideVersion;
use Illuminate\Support\Facades\View;

/**
 * Everything the Template Manager does to a template, in one place, so the
 * controller stays a controller and every mutation gets the same treatment:
 * validate first, persist second, write a version, write the audit log.
 *
 * There is exactly one rule here that matters: nothing reaches
 * template_overrides.source without passing TemplateValidator. Publish, revert
 * and preview all funnel through the same gate, because a revert to a version
 * saved before a validator improvement is still merchant code being put in
 * front of customers.
 */
class TemplateManager
{
    public function __construct(
        private TemplateValidator $validator,
        private TemplateOverrideResolver $resolver,
    ) {}

    /* ------------------------------------------------------------------ *
     * Catalogue
     * ------------------------------------------------------------------ */

    /**
     * The editable template catalogue, decorated with live state.
     *
     * Returns groups of rows the index view can render directly, because a
     * view must not compute this itself.
     */
    public function catalogue(): array
    {
        $overridden = array_flip($this->resolver->overriddenViews());
        $groups = [];

        foreach ((array) config('templates.groups', []) as $key => $group) {
            $rows = [];

            foreach ((array) ($group['views'] ?? []) as $view => $meta) {
                [$label, $description, $risk] = array_pad((array) $meta, 3, 'normal');

                $rows[] = [
                    'view' => $view,
                    'label' => $label,
                    'description' => $description,
                    'risk' => $risk,
                    'overridden' => isset($overridden[$view]),
                    'exists' => $this->shippedPath($view) !== null,
                ];
            }

            $groups[$key] = [
                'key' => $key,
                'label' => $group['label'] ?? ucfirst($key),
                'icon' => $group['icon'] ?? 'edit',
                'description' => $group['description'] ?? '',
                'rows' => $rows,
                'overridden_count' => count(array_filter($rows, fn ($r) => $r['overridden'])),
            ];
        }

        return $groups;
    }

    /** Metadata for one editable view, or null when it is not in the catalogue. */
    public function meta(string $view): ?array
    {
        foreach ((array) config('templates.groups', []) as $key => $group) {
            if (isset($group['views'][$view])) {
                [$label, $description, $risk] = array_pad((array) $group['views'][$view], 3, 'normal');

                return [
                    'view' => $view,
                    'label' => $label,
                    'description' => $description,
                    'risk' => $risk,
                    'group' => $group['label'] ?? ucfirst($key),
                    'group_key' => $key,
                ];
            }
        }

        return null;
    }

    public function isEditable(string $view): bool
    {
        return $this->meta($view) !== null;
    }

    /* ------------------------------------------------------------------ *
     * Reading
     * ------------------------------------------------------------------ */

    /** The template as ShopMGR ships it, straight off the release's filesystem. */
    public function shippedSource(string $view): string
    {
        $path = $this->shippedPath($view);

        return $path ? (string) @file_get_contents($path) : '';
    }

    /**
     * The shipped file's path, resolved against the real view paths rather than
     * through the finder (the finder is exactly what we are bypassing here).
     */
    public function shippedPath(string $view): ?string
    {
        $relative = str_replace('.', '/', $view);

        foreach (View::getFinder()->getPaths() as $base) {
            foreach (['.blade.php', '.php'] as $extension) {
                $candidate = rtrim($base, '/').'/'.$relative.$extension;

                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public function override(string $view): ?TemplateOverride
    {
        return TemplateOverride::where('view', $view)->first();
    }

    /** What is live right now: the override if there is one, else the shipped file. */
    public function currentSource(string $view): string
    {
        return $this->override($view)?->source ?? $this->shippedSource($view);
    }

    public function versions(string $view)
    {
        return TemplateOverrideVersion::with('user')
            ->where('view', $view)
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    /* ------------------------------------------------------------------ *
     * Writing
     * ------------------------------------------------------------------ */

    /**
     * Publish an edit.
     *
     * @return array{ok: bool, error: ?string, line: ?int, excerpt: ?array, exact: bool}
     */
    public function publish(string $view, string $source, ?string $note = null, string $action = 'save'): array
    {
        $result = $this->validator->validate($source);

        if (! $result['ok']) {
            return $result;
        }

        $source = $this->normalise($source);

        $override = TemplateOverride::updateOrCreate(
            ['view' => $view],
            ['source' => $source, 'updated_by' => auth()->id()]
        );

        TemplateOverrideVersion::create([
            'template_override_id' => $override->id,
            'view' => $view,
            'source' => $source,
            'action' => $action,
            'note' => $note,
            'user_id' => auth()->id(),
        ]);

        $this->resolver->forget();
        $this->resolver->purge($view);

        AuditLog::record(
            'template.'.$action,
            ucfirst($action).' template override for "'.$view.'"'.($note ? ' ('.$note.')' : ''),
            $override
        );

        return $result;
    }

    /**
     * Roll back to a stored version.
     *
     * History is append-only: this writes the old source forward as a new
     * version rather than deleting anything, so a revert is itself revertable.
     */
    public function revert(string $view, TemplateOverrideVersion $version): array
    {
        if ($version->view !== $view || $version->source === null) {
            return ['ok' => false, 'error' => 'That version does not belong to this template.', 'line' => null, 'excerpt' => null, 'exact' => true];
        }

        return $this->publish($view, $version->source, 'Reverted to version #'.$version->id, 'revert');
    }

    /**
     * Drop the override entirely and go back to the shipped template.
     *
     * The version history survives on purpose (the versions table keeps the
     * view name and nulls its parent), so a reset is not a shredder.
     */
    public function reset(string $view): void
    {
        $override = $this->override($view);

        if (! $override) {
            return;
        }

        TemplateOverrideVersion::create([
            'template_override_id' => $override->id,
            'view' => $view,
            'source' => null,
            'action' => 'reset',
            'note' => 'Reset to shipped default',
            'user_id' => auth()->id(),
        ]);

        AuditLog::record('template.reset', 'Reset template "'.$view.'" to the shipped default', $override);

        $override->delete();

        $this->resolver->purge($view);
        $this->resolver->forget();
    }

    /* ------------------------------------------------------------------ *
     * Preview
     * ------------------------------------------------------------------ */

    /**
     * Stage an unpublished draft for this session only.
     *
     * Validated with exactly the same gate as a publish: a preview that can 500
     * the admin's own browsing session is not a preview, it is a smaller
     * outage.
     */
    public function preview(string $view, string $source): array
    {
        $result = $this->validator->validate($source);

        if (! $result['ok']) {
            return $result;
        }

        // A draft lives in the session. On the cookie session driver that means
        // it lives in a 4KB cookie, so a real template would be silently
        // truncated and the merchant would preview something that is not what
        // they typed. Say so instead.
        if (config('session.driver') === 'cookie' && strlen($source) > 3000) {
            return [
                'ok' => false,
                'error' => 'This template is too large to preview while the session driver is set to "cookie". '
                    .'Switch the session driver to "database" or "file", or save the template instead.',
                'line' => null,
                'excerpt' => null,
                'exact' => true,
            ];
        }

        $drafts = session('template_preview', []);
        $drafts = is_array($drafts) ? $drafts : [];
        $drafts[$view] = ['source' => $this->normalise($source), 'at' => time()];

        session(['template_preview' => $drafts]);

        return $result;
    }

    public function stopPreview(?string $view = null): void
    {
        if ($view === null) {
            session()->forget('template_preview');

            return;
        }

        $drafts = session('template_preview', []);
        unset($drafts[$view]);
        session(['template_preview' => $drafts]);
    }

    /** Views currently being previewed in this session. */
    public function previewing(): array
    {
        return array_keys($this->resolver->previewDrafts());
    }

    /* ------------------------------------------------------------------ *
     * Available Variables reference
     * ------------------------------------------------------------------ */

    /**
     * The editor's "Available Variables" reference for one view.
     *
     * This is a HELPER derived by reading the shipped template, not a runtime
     * guarantee: it parses the shipped source for the inputs a controller feeds
     * this view, folds in the storefront view-composer variables every shop page
     * receives, and lists the components and Blade a merchant can reach for. It
     * never executes the template, so treat the variable list as a strong hint,
     * not a contract.
     *
     * @return array{
     *     scoped: list<array{name: string, desc: ?string}>,
     *     shared: list<array{name: string, desc: ?string}>,
     *     shared_applies: bool,
     *     shared_note: string,
     *     components: list<array{tag: string, attrs: list<string>}>,
     *     blade: list<array{code: string, desc: string}>,
     *     helpers: list<array{code: string, desc: string}>
     * }
     */
    public function availableVariables(string $view): array
    {
        $source = $this->shippedSource($view);
        $descriptions = $this->variableDescriptions();

        // 1. Variables in scope for THIS template, read off the shipped source.
        $scoped = [];
        foreach ($this->scopedVariables($source) as $name) {
            $scoped[] = ['name' => $name, 'desc' => $descriptions[$name] ?? null];
        }

        // 2. Globally shared storefront composer variables (if this view gets
        //    them) plus the validation bag, which is available in every view.
        $sharedInfo = $this->sharedVariables($view);
        $shared = [];
        // The composer variables only reach storefront views (shop.* and the
        // shop layout). A template outside that surface (an email, an admin
        // screen) does not get them, so it must not be told it does.
        if ($sharedInfo['applies']) {
            foreach ($sharedInfo['keys'] as $name) {
                // Do not repeat a shared variable that the template also names
                // directly; the scoped list already showed it.
                if (in_array($name, array_column($scoped, 'name'), true)) {
                    continue;
                }
                $shared[] = ['name' => $name, 'desc' => $descriptions[$name] ?? null];
            }
        }
        // $errors is available in every rendered view, storefront or not.
        if (! in_array('errors', array_column($scoped, 'name'), true)) {
            $shared[] = ['name' => 'errors', 'desc' => $descriptions['errors']];
        }

        return [
            'scoped' => $scoped,
            'shared' => $shared,
            'shared_applies' => $sharedInfo['applies'],
            'shared_note' => $sharedInfo['applies']
                ? 'Shared with every storefront page by a view composer, so these are available here whether or not the template mentions them.'
                : 'This template is outside the storefront, so it does not receive the shared shop variables. Only $errors is always present.',
            'components' => $this->componentCatalogue(),
            'blade' => $this->bladeCheatSheet(),
            'helpers' => $this->helperCheatSheet(),
        ];
    }

    /**
     * Real input variables named in a template's source.
     *
     * Everything Blade, Alpine, loop-bound or locally assigned is filtered out,
     * so what returns is the set a controller (or composer) must supply. Returned
     * in first-appearance order, deduplicated.
     *
     * @return list<string>
     */
    private function scopedVariables(string $source): array
    {
        // Names that are never a controller input: Blade/Laravel internals and
        // Alpine's magic properties (which appear as $refs, $event, ... in the
        // markup but are JavaScript, not PHP).
        $noise = array_flip([
            'loop', 'slot', 'attributes', 'errors', 'message', 'component', 'this',
            'refs', 'event', 'el', 'dispatch', 'store', 'watch', 'nextTick',
            'root', 'id', 'data', 'wire', 'nexttick',
        ]);

        $excluded = $noise;

        // foreach / forelse value + key binds: `as $x`, `as $k => $v`,
        // and list destructuring `as [$a, $b]`.
        if (preg_match_all('/\bas\s+\$(\w+)\s*=>\s*\$(\w+)/', $source, $m)) {
            foreach (array_merge($m[1], $m[2]) as $name) {
                $excluded[$name] = true;
            }
        }
        if (preg_match_all('/\bas\s+\$(\w+)(?!\s*=>)/', $source, $m)) {
            foreach ($m[1] as $name) {
                $excluded[$name] = true;
            }
        }
        if (preg_match_all('/\bas\s*\[([^\]]*)\]/', $source, $m)) {
            foreach ($m[1] as $chunk) {
                if (preg_match_all('/\$(\w+)/', $chunk, $inner)) {
                    foreach ($inner[1] as $name) {
                        $excluded[$name] = true;
                    }
                }
            }
        }

        // Locally assigned values: `@php($x = ...)`, `$x = ...`. A single `=`
        // that is not `==`, `=>`, `<=`, `>=`, `!=` (those are comparisons where
        // the variable is read, not written).
        if (preg_match_all('/\$(\w+)\s*=(?![=>])/', $source, $m)) {
            foreach ($m[1] as $name) {
                $excluded[$name] = true;
            }
        }

        $result = [];
        if (preg_match_all('/\$([a-zA-Z_]\w*)/', $source, $m)) {
            foreach ($m[1] as $name) {
                if (isset($excluded[$name])) {
                    continue;
                }
                if (str_starts_with($name, '__')) {
                    continue; // $__env, $__data, ...
                }
                $result[$name] = true;
            }
        }

        return array_keys($result);
    }

    /**
     * The storefront view-composer variables and whether this view receives them.
     *
     * Read from AppServiceProvider so the list tracks the composer rather than a
     * copy of it: the composer's target patterns decide "applies", its `->with`
     * keys decide the names.
     *
     * @return array{keys: list<string>, applies: bool, patterns: list<string>}
     */
    private function sharedVariables(string $view): array
    {
        $path = app_path('Providers/AppServiceProvider.php');
        $src = is_file($path) ? (string) @file_get_contents($path) : '';

        $patterns = [];
        if (preg_match('/View::composer\(\s*\[([^\]]*)\]/', $src, $m)) {
            if (preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1], $pm)) {
                $patterns = $pm[1];
            }
        }

        $keys = [];
        if (preg_match_all('/->with\(\s*\[(.*?)\]\s*\)/s', $src, $withs)) {
            foreach ($withs[1] as $body) {
                if (preg_match_all('/[\'"]([a-zA-Z_]\w*)[\'"]\s*=>/', $body, $km)) {
                    foreach ($km[1] as $k) {
                        $keys[$k] = true;
                    }
                }
            }
        }

        // Sensible fallback if the provider could not be parsed for any reason.
        if ($keys === []) {
            foreach (['navCollections', 'cartCount', 'storeName', 'currentCustomer', 'maxWidth', 'themeLogo'] as $k) {
                $keys[$k] = true;
            }
        }
        if ($patterns === []) {
            $patterns = ['components.layouts.shop', 'shop.*'];
        }

        $applies = false;
        foreach ($patterns as $pattern) {
            if (\Illuminate\Support\Str::is($pattern, $view)) {
                $applies = true;
                break;
            }
        }

        return ['keys' => array_keys($keys), 'applies' => $applies, 'patterns' => $patterns];
    }

    /**
     * The `<x-...>` components a merchant can drop into a template.
     *
     * One entry per file under resources/views/components, minus the chrome and
     * plumbing components that only make sense inside the app shell. Attribute
     * names come from each component's @props.
     *
     * @return list<array{tag: string, attrs: list<string>}>
     */
    private function componentCatalogue(): array
    {
        $base = resource_path('views/components');
        if (! is_dir($base)) {
            return [];
        }

        // Framework/chrome components a storefront template would never place by
        // hand; hiding them keeps the list to things worth reaching for.
        $hidden = array_flip([
            'accent-style', 'favicon-links', 'tailwind-cdn', 'demo-banner',
            'license-banner', 'update-banner', 'preview-badge', 'seo', 'seo-panel',
            'settings-tabs', 'side-menu', 'layouts.app', 'layouts.shop',
            'layouts.shop-auth', 'confirm-action', 'data-surface',
        ]);

        $items = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace(
                [$base.'/', '.blade.php', '/'],
                ['', '', '.'],
                $file->getPathname()
            );

            if (isset($hidden[$relative])) {
                continue;
            }

            $attrs = [];
            $contents = (string) @file_get_contents($file->getPathname());
            if (preg_match('/@props\(\s*\[(.*?)\]\s*\)/s', $contents, $m)) {
                // A quoted token immediately after `[` or `,` is a prop name;
                // tokens after `=>` are default values, not names.
                if (preg_match_all('/(?:\[|,)\s*[\'"]([a-zA-Z_][\w-]*)[\'"]/', '['.$m[1], $pm)) {
                    $attrs = array_values(array_unique($pm[1]));
                }
            }

            $items[] = ['tag' => 'x-'.$relative, 'attrs' => $attrs];
        }

        usort($items, fn ($a, $b) => $a['tag'] <=> $b['tag']);

        return $items;
    }

    /** A tight Blade cheat sheet, not a manual. */
    private function bladeCheatSheet(): array
    {
        return [
            ['code' => '{{ $var }}', 'desc' => 'Print a value, safely escaped.'],
            ['code' => '{!! $html !!}', 'desc' => 'Print unescaped HTML. Only for trusted values.'],
            ['code' => '@if (...) ... @elseif (...) ... @else ... @endif', 'desc' => 'Conditionals.'],
            ['code' => '@foreach ($items as $item) ... @endforeach', 'desc' => 'Loop a collection. $loop is available inside.'],
            ['code' => '@forelse ($items as $item) ... @empty ... @endforelse', 'desc' => 'Loop with a fallback when empty.'],
            ['code' => '@isset($var) ... @endisset', 'desc' => 'Render only when a variable is set.'],
            ['code' => '@php $x = ...; @endphp', 'desc' => 'A small block of PHP. Keep logic light.'],
            ['code' => '@auth(\'customer\') ... @endauth', 'desc' => 'Only for a signed-in customer.'],
        ];
    }

    /** Store helpers that already appear in the shipped storefront views. */
    private function helperCheatSheet(): array
    {
        return [
            ['code' => "route('shop.product', \$product)", 'desc' => 'URL to a product page.'],
            ['code' => "route('shop.catalog')", 'desc' => 'URL to the all-products catalog.'],
            ['code' => "route('shop.cart.add')", 'desc' => 'Form action to add to the cart.'],
            ['code' => '<x-money :amount="$order->total" />', 'desc' => 'Format an amount as store currency.'],
            ['code' => '$order->total_formatted', 'desc' => 'A pre-formatted money string on a model.'],
            ['code' => "\$date->format(config('shop.date_format'))", 'desc' => "Format a date in the store's chosen format."],
            ['code' => "config('shop.store_name')", 'desc' => 'Read a store setting.'],
            ['code' => "\\Illuminate\\Support\\Str::plural('Item', \$count)", 'desc' => 'Pluralise a word by a count.'],
        ];
    }

    /**
     * Short, curated one-liners for the variables merchants meet most. Anything
     * not here falls back to just its name, which is honest: we would rather show
     * no description than a guessed one.
     *
     * @return array<string, string>
     */
    private function variableDescriptions(): array
    {
        return [
            // Shared / composer.
            'navCollections' => 'Collections shown in the header navigation.',
            'cartCount' => 'Number of items currently in the cart.',
            'storeName' => 'Your store name.',
            'currentCustomer' => 'The signed-in customer, or null for a guest.',
            'maxWidth' => "The page-width utility class (e.g. max-w-6xl).",
            'themeLogo' => 'The uploaded logo URL, or null if none is set.',
            'errors' => 'The validation error bag for the last submitted form.',
            // Storefront inputs.
            'product' => 'The product being shown.',
            'products' => 'The products in this list or grid.',
            'collection' => 'The collection being shown.',
            'collections' => 'The list of collections.',
            'featured' => 'Featured products for the home page.',
            'order' => 'A single order.',
            'orders' => "The customer's orders.",
            'customer' => 'The signed-in customer.',
            'cart' => 'The current shopping cart.',
            'defaultAddress' => "The customer's default address.",
            'addresses' => "The customer's saved addresses.",
            'address' => 'A single saved address.',
            'optionAxes' => 'The product option axes (e.g. Size, Colour).',
            'variantMap' => 'Map of option selections to variants, for the picker.',
            'defaultVariant' => 'The variant selected when the page first loads.',
            'relatedProducts' => 'Products related to the one being viewed.',
            'paymentLine' => 'A human summary of how the order was paid.',
            'isTestOrder' => 'True when the order was placed in Stripe test mode.',
            'shippingMethods' => 'The shipping options offered at checkout.',
            'total' => 'The order total.',
            'subtotal' => 'The order subtotal, before tax and shipping.',
        ];
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    /** Normalise line endings so diffs are about content, not about Windows. */
    private function normalise(string $source): string
    {
        return str_replace(["\r\n", "\r"], "\n", $source);
    }
}
