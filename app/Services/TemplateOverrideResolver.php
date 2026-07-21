<?php

namespace App\Services;

use App\Models\TemplateOverride;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Decides, for any Blade view name, whether the DATABASE has something to say
 * about it - and hands back a file path if so.
 *
 * Blade's compiler is built around files: it stats a path, compares mtimes, and
 * caches by path hash. So the override layer keeps the database as the source
 * of truth and materialises each override to a stable path under
 * storage/app/templates. Writing the file updates its mtime, which is exactly
 * the signal Blade already uses to recompile, so no cache plumbing is needed.
 *
 * Resolution order (first hit wins):
 *
 *   1. A live PREVIEW draft belonging to the current admin's session. Scoped to
 *      one user and one session so a merchant can look at an unpublished edit
 *      on the real storefront without showing it to a single customer.
 *   2. The PUBLISHED override row.
 *   3. Nothing - the caller falls through to the shipped file on disk.
 */
class TemplateOverrideResolver
{
    /** Per-request memo so one request never queries the same view twice. */
    private array $resolved = [];

    private ?array $mapCache = null;

    public const CACHE_KEY = 'templates.overrides.map';

    /**
     * Path to serve for this view, or null to fall through to the filesystem.
     */
    public function pathFor(string $view): ?string
    {
        if (array_key_exists($view, $this->resolved)) {
            return $this->resolved[$view];
        }

        return $this->resolved[$view] = $this->resolve($view);
    }

    private function resolve(string $view): ?string
    {
        if ($draft = $this->previewDraft($view)) {
            return $this->materialise($this->previewPath($view), $draft['source'], (int) $draft['at']);
        }

        $map = $this->map();

        if (! isset($map[$view])) {
            return null;
        }

        return $this->materialise(
            $this->publishedPath($view),
            $map[$view]['source'],
            (int) $map[$view]['updated_at']
        );
    }

    /**
     * All published overrides, cached. Cheap enough to hold whole: a merchant
     * overrides a handful of templates, not a library of them.
     *
     * @return array<string, array{source: string, updated_at: int}>
     */
    public function map(): array
    {
        if ($this->mapCache !== null) {
            return $this->mapCache;
        }

        try {
            // Deliberately no Schema::hasTable() guard here: that is an
            // information_schema query on every single page view, and the
            // cached map means the real query runs about once. A missing table
            // throws, and the catch below is the guard.
            return $this->mapCache = Cache::rememberForever(self::CACHE_KEY, function () {
                return TemplateOverride::query()
                    ->get(['view', 'source', 'updated_at'])
                    ->mapWithKeys(fn ($o) => [$o->view => [
                        'source' => (string) $o->source,
                        'updated_at' => $o->updated_at?->getTimestamp() ?? time(),
                    ]])
                    ->all();
            });
        } catch (\Throwable $e) {
            // DB not ready (install, migration, maintenance): the shipped
            // templates must still render.
            return $this->mapCache = [];
        }
    }

    /** Names of every currently overridden view. */
    public function overriddenViews(): array
    {
        return array_keys($this->map());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->mapCache = null;
        $this->resolved = [];
    }

    /* ------------------------------------------------------------------ *
     * Preview drafts
     * ------------------------------------------------------------------ */

    /**
     * The current session's unpublished draft for this view, if any and if it
     * has not expired.
     */
    private function previewDraft(string $view): ?array
    {
        $drafts = $this->previewDrafts();

        return $drafts[$view] ?? null;
    }

    /** @return array<string, array{source: string, at: int}> */
    public function previewDrafts(): array
    {
        try {
            if (! app()->runningInConsole() && app('session')->isStarted()) {
                $drafts = session('template_preview', []);
            } else {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        if (! is_array($drafts) || ! $drafts) {
            return [];
        }

        $ttl = (int) config('templates.preview_minutes', 20) * 60;
        $now = time();

        return array_filter($drafts, fn ($d) => is_array($d) && ($now - (int) ($d['at'] ?? 0)) < $ttl);
    }

    /* ------------------------------------------------------------------ *
     * Materialisation
     * ------------------------------------------------------------------ */

    public function publishedPath(string $view): string
    {
        return $this->directory('published').'/'.$this->slug($view).'.blade.php';
    }

    private function previewPath(string $view): string
    {
        $user = (int) (auth()->id() ?: 0);

        return $this->directory('preview/'.$user).'/'.$this->slug($view).'.blade.php';
    }

    /**
     * Write the source to disk only when the copy on disk is missing or older
     * than the row. Rewriting on every request would invalidate Blade's
     * compiled cache on every request.
     */
    private function materialise(string $path, string $source, int $stamp): ?string
    {
        try {
            if (is_file($path) && filemtime($path) >= $stamp && md5_file($path) === md5($source)) {
                return $path;
            }

            File::ensureDirectoryExists(dirname($path), 0775);
            File::put($path, $source);
            @touch($path, max($stamp, time()));

            return $path;
        } catch (\Throwable $e) {
            // If we cannot write the override, serving the shipped template is
            // the correct failure mode: the shop stays up.
            return null;
        }
    }

    /** Remove the materialised copy of a published override. */
    public function purge(string $view): void
    {
        @unlink($this->publishedPath($view));
        $this->forget();
    }

    private function directory(string $sub): string
    {
        $dir = storage_path('app/templates/'.$sub);
        File::ensureDirectoryExists($dir, 0775);

        return $dir;
    }

    private function slug(string $view): string
    {
        return preg_replace('/[^A-Za-z0-9_]+/', '_', $view);
    }
}
