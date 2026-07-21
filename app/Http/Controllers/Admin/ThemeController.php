<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Theme;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Appearance -> Themes.
 *
 * A theme is data, not code, so this is not locked to admins the way the
 * Template Manager is - any signed-in staff member with access to the admin can
 * restyle the shop. Deleting themes and activating them are still audited.
 */
class ThemeController extends Controller
{
    public function __construct(private ThemeService $themes) {}

    public function index()
    {
        $this->themes->ensurePresets();

        return view('admin.themes.index', [
            'themes' => Theme::orderByDesc('is_active')->orderByDesc('is_preset')->orderBy('name')->get(),
            'previewId' => $this->themes->previewId(),
        ]);
    }

    public function create()
    {
        $theme = new Theme([
            'name' => '',
            'description' => null,
            'tokens' => Theme::defaultTokens(),
        ]);

        return view('admin.themes.edit', $this->formData($theme));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $theme = new Theme();
        $this->fill($theme, $data, $request);
        $theme->save();

        AuditLog::record('theme.create', 'Created theme "'.$theme->name.'"', $theme);

        return redirect()->route('themes.edit', $theme)->with('status', 'Theme created.');
    }

    public function edit(Theme $theme)
    {
        return view('admin.themes.edit', $this->formData($theme));
    }

    public function update(Request $request, Theme $theme)
    {
        $data = $this->validated($request, $theme);

        $this->fill($theme, $data, $request);
        $theme->save();

        $this->themes->forget();

        AuditLog::record('theme.update', 'Updated theme "'.$theme->name.'"', $theme);

        return redirect()->route('themes.edit', $theme)->with('status', 'Theme saved.');
    }

    /** Make this theme the storefront default. */
    public function activate(Theme $theme)
    {
        $this->themes->activate($theme);
        $this->themes->stopPreview();

        AuditLog::record('theme.activate', 'Activated theme "'.$theme->name.'"', $theme);

        return redirect()->route('themes.index')->with('status', '"'.$theme->name.'" is now the active theme.');
    }

    /** See a theme on the real site without activating it, for this session only. */
    public function preview(Theme $theme)
    {
        $this->themes->startPreview($theme);

        return back()->with('status', 'Previewing "'.$theme->name.'". Only your session sees it.');
    }

    public function stopPreview()
    {
        $this->themes->stopPreview();

        return back()->with('status', 'Theme preview stopped.');
    }

    public function duplicate(Theme $theme)
    {
        $copy = $theme->replicate(['is_active', 'is_preset']);
        $copy->name = Str::limit($theme->name.' Copy', 60, '');
        $copy->slug = $this->uniqueSlug($copy->name);
        $copy->is_active = false;
        $copy->is_preset = false;
        $copy->save();

        AuditLog::record('theme.duplicate', 'Duplicated theme "'.$theme->name.'"', $copy);

        return redirect()->route('themes.edit', $copy)->with('status', 'Theme duplicated. Edit the copy freely.');
    }

    /** Download the theme as portable JSON. */
    public function export(Theme $theme)
    {
        $payload = json_encode($this->themes->export($theme), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($payload, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="shopmgr-theme-'.$theme->slug.'.json"',
        ]);
    }

    /** Import a theme export, from an uploaded file or pasted JSON. */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['nullable', 'file', 'max:512'],
            'json' => ['nullable', 'string', 'max:200000'],
        ]);

        $json = $request->hasFile('file')
            ? (string) file_get_contents($request->file('file')->getRealPath())
            : (string) $request->input('json');

        if (trim($json) === '') {
            return back()->with('warning', 'Choose a theme file or paste its JSON first.');
        }

        $parsed = $this->themes->parseImport($json);

        if (! $parsed['ok']) {
            return back()->with('warning', $parsed['error']);
        }

        $theme = new Theme([
            'name' => $parsed['name'] ?: 'Imported Theme',
            'slug' => $this->uniqueSlug($parsed['name'] ?: 'Imported Theme'),
            'description' => $parsed['description'],
            'tokens' => $parsed['tokens'],
            'is_active' => false,
            'is_preset' => false,
        ]);
        $theme->save();

        AuditLog::record('theme.import', 'Imported theme "'.$theme->name.'"', $theme);

        return redirect()->route('themes.edit', $theme)->with('status', 'Theme imported.');
    }

    public function destroy(Theme $theme)
    {
        if ($theme->is_preset) {
            return back()->with('warning', 'Shipped presets cannot be deleted. Duplicate one instead.');
        }

        if ($theme->is_active) {
            return back()->with('warning', 'Activate another theme before deleting this one.');
        }

        AuditLog::record('theme.delete', 'Deleted theme "'.$theme->name.'"', $theme);
        $theme->delete();

        return redirect()->route('themes.index')->with('status', 'Theme deleted.');
    }

    /** massSelect bulk delete from the themes table. */
    public function bulkDestroy(Request $request)
    {
        $ids = (array) $request->input('ids', []);

        $deletable = Theme::whereIn('id', $ids)
            ->where('is_preset', false)
            ->where('is_active', false)
            ->get();

        foreach ($deletable as $theme) {
            AuditLog::record('theme.delete', 'Deleted theme "'.$theme->name.'"', $theme);
            $theme->delete();
        }

        $skipped = count($ids) - $deletable->count();

        return redirect()->route('themes.index')->with(
            'status',
            'Deleted '.$deletable->count().' theme'.($deletable->count() === 1 ? '' : 's').'.'
            .($skipped > 0 ? ' '.$skipped.' skipped (active or shipped preset).' : '')
        );
    }

    /* ------------------------------------------------------------------ *
     * Form plumbing
     * ------------------------------------------------------------------ */

    private function formData(Theme $theme): array
    {
        return [
            'theme' => $theme,
            'tokens' => $theme->tokens(),
            'fonts' => $this->themes->fontChoices(),
            'ramp' => $this->themes->ramp($theme->tokens()),
            'exportJson' => $theme->exists
                ? json_encode($this->themes->export($theme), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : null,
        ];
    }

    private function validated(Request $request, ?Theme $theme = null): array
    {
        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:200'],
            'accent' => $hex,
            'derive_ramp' => ['nullable', 'boolean'],
            'chrome' => $hex,
            'chrome_soft' => $hex,
            'shop_bg' => $hex,
            'shop_ink' => $hex,
            'shop_muted' => $hex,
            'shop_line' => $hex,
            'font_family' => ['required', 'in:instrument,system,serif,mono'],
            'font_scale' => ['required', 'integer', 'min:85', 'max:125'],
            'radius' => ['required', 'integer', 'min:0', 'max:220'],
            'spacing' => ['required', 'integer', 'min:70', 'max:160'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'favicon' => ['nullable', 'image', 'max:512'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);
    }

    private function fill(Theme $theme, array $data, Request $request): void
    {
        $existing = $theme->tokens();

        // "Derive ramp from accent" nulls the explicit ramp, which is what makes
        // the accent picker actually do something on a preset that ships with a
        // hand-tuned scale.
        $ramp = ! empty($data['derive_ramp']) ? null : ($existing['ramp'] ?? null);

        $theme->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'tokens' => [
                'accent' => strtolower($data['accent']),
                'ramp' => $ramp,
                'chrome' => strtolower($data['chrome']),
                'chrome_soft' => strtolower($data['chrome_soft']),
                'shop_bg' => strtolower($data['shop_bg']),
                'shop_ink' => strtolower($data['shop_ink']),
                'shop_muted' => strtolower($data['shop_muted']),
                'shop_line' => strtolower($data['shop_line']),
                'font_family' => $data['font_family'],
                'font_scale' => (int) $data['font_scale'],
                'radius' => (int) $data['radius'],
                'spacing' => (int) $data['spacing'],
            ],
        ]);

        if (! $theme->slug) {
            $theme->slug = $this->uniqueSlug($data['name']);
        }

        if (! empty($data['remove_logo'])) {
            $theme->logo_path = null;
        }

        if (! empty($data['remove_favicon'])) {
            $theme->favicon_path = null;
        }

        if ($request->hasFile('logo')) {
            $theme->logo_path = $this->storeUpload($request->file('logo'), 'logo');
        }

        if ($request->hasFile('favicon')) {
            $theme->favicon_path = $this->storeUpload($request->file('favicon'), 'favicon');
        }
    }

    /**
     * Theme assets go in public/uploads/themes so they are served straight by
     * the web server; no storage:link, which is one less thing to be broken on
     * a merchant's own install.
     */
    private function storeUpload($file, string $kind): string
    {
        $dir = public_path('uploads/themes');
        File::ensureDirectoryExists($dir, 0775);

        $name = $kind.'-'.Str::random(12).'.'.strtolower($file->getClientOriginalExtension() ?: 'png');
        $file->move($dir, $name);

        return 'uploads/themes/'.$name;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'theme';
        $slug = $base;
        $n = 2;

        while (Theme::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
