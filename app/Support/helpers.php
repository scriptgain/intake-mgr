<?php

use App\Services\UpdateService;

if (! function_exists('asset_v')) {
    /**
     * Cache-busted asset URL. Uses the file's actual mtime, NEVER time() — a
     * known fleet bug (reference_asset_v_cachebust) where a time()-based query
     * string changes on every request and defeats the browser cache, forcing a
     * re-download of every asset on every page load.
     *
     * Falls back to the app VERSION string when the file cannot be stat'd (e.g.
     * missing from a fresh checkout), so the helper never throws.
     */
    function asset_v(string $path): string
    {
        $full = public_path($path);

        $version = file_exists($full)
            ? (string) filemtime($full)
            : (class_exists(UpdateService::class) ? UpdateService::currentVersion() : '1');

        return asset($path).'?v='.$version;
    }
}
