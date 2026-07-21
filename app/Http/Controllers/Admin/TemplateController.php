<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TemplateOverrideVersion;
use App\Services\DiffService;
use App\Services\TemplateManager;
use Illuminate\Http\Request;

/**
 * Appearance -> Templates.
 *
 * Admin-only, without exception. Editing a Blade template is editing code that
 * runs on this server, so this is gated on the admin role rather than merely on
 * being signed in: a staff account that can process refunds must not also be
 * able to run PHP.
 */
class TemplateController extends Controller
{
    public function __construct(
        private TemplateManager $templates,
        private DiffService $diff,
    ) {}

    private function guard(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Editing templates is restricted to admins.');
    }

    /** The grouped list of editable templates and their override state. */
    public function index()
    {
        $this->guard();

        return view('admin.templates.index', [
            'groups' => $this->templates->catalogue(),
            'previewing' => $this->templates->previewing(),
        ]);
    }

    /** The editor for one template. */
    public function edit(string $view)
    {
        $this->guard();

        $meta = $this->templates->meta($view);
        abort_if($meta === null, 404, 'That template is not editable.');

        $shipped = $this->templates->shippedSource($view);
        $override = $this->templates->override($view);
        $current = $override?->source ?? $shipped;

        return view('admin.templates.edit', [
            'meta' => $meta,
            'override' => $override,
            'source' => old('source', $current),
            'shipped' => $shipped,
            'current' => $current,
            'versions' => $this->templates->versions($view),
            'diff' => $this->diff->lines($shipped, $current),
            'previewing' => in_array($view, $this->templates->previewing(), true),
            'lineCount' => substr_count($current, "\n") + 1,
            'reference' => $this->templates->availableVariables($view),
        ]);
    }

    /** Publish an edit. Rejected saves never touch the database. */
    public function update(Request $request, string $view)
    {
        $this->guard();
        abort_unless($this->templates->isEditable($view), 404);

        $data = $request->validate([
            'source' => ['required', 'string', 'max:600000'],
            'note' => ['nullable', 'string', 'max:160'],
        ]);

        $result = $this->templates->publish($view, $data['source'], $data['note'] ?? null);

        if (! $result['ok']) {
            return back()
                ->withInput()
                ->with('template_error', $result);
        }

        return redirect()
            ->route('templates.edit', $view)
            ->with('status', 'Template saved and live.');
    }

    /**
     * Stage the edit as a session-scoped draft and send the merchant to look at
     * it on the real storefront. Validated exactly like a publish.
     */
    public function preview(Request $request, string $view)
    {
        $this->guard();
        abort_unless($this->templates->isEditable($view), 404);

        $data = $request->validate(['source' => ['required', 'string', 'max:600000']]);

        $result = $this->templates->preview($view, $data['source']);

        if (! $result['ok']) {
            return back()->withInput()->with('template_error', $result);
        }

        return redirect()
            ->route('templates.edit', $view)
            ->with('status', 'Preview active for your session only. Open the storefront to see it.');
    }

    public function stopPreview(Request $request)
    {
        $this->guard();

        $this->templates->stopPreview($request->input('view'));

        return back()->with('status', 'Preview stopped.');
    }

    /** Roll back to a stored version. */
    public function revert(string $view, TemplateOverrideVersion $version)
    {
        $this->guard();
        abort_unless($this->templates->isEditable($view), 404);

        $result = $this->templates->revert($view, $version);

        if (! $result['ok']) {
            return back()->with('template_error', $result);
        }

        return redirect()
            ->route('templates.edit', $view)
            ->with('status', 'Reverted to version #'.$version->id.'.');
    }

    /** Drop the override; the shipped template takes over again immediately. */
    public function reset(string $view)
    {
        $this->guard();
        abort_unless($this->templates->isEditable($view), 404);

        $this->templates->reset($view);
        $this->templates->stopPreview($view);

        return redirect()
            ->route('templates.edit', $view)
            ->with('status', 'Reset to the shipped default.');
    }

    /** A single version, diffed against what is live right now. */
    public function version(string $view, TemplateOverrideVersion $version)
    {
        $this->guard();

        $meta = $this->templates->meta($view);
        abort_if($meta === null, 404);
        abort_if($version->view !== $view, 404);

        $current = $this->templates->currentSource($view);

        return view('admin.templates.version', [
            'meta' => $meta,
            'version' => $version,
            'diff' => $this->diff->lines($version->source ?? '', $current),
            'canRevert' => $version->source !== null,
        ]);
    }
}
