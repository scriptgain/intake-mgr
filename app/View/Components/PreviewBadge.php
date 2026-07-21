<?php

namespace App\View\Components;

use App\Services\TemplateOverrideResolver;
use App\Services\ThemeService;
use Illuminate\View\Component;

/**
 * A fixed pill shown to whoever is previewing an unpublished template or an
 * inactive theme.
 *
 * Without it, a preview is indistinguishable from the live site, and the
 * predictable outcome is a merchant filing a support ticket about a storefront
 * change nobody else can see.
 */
class PreviewBadge extends Component
{
    public array $items = [];

    public function __construct(ThemeService $themes, TemplateOverrideResolver $resolver)
    {
        try {
            if (! auth()->check()) {
                return;
            }

            if ($theme = $themes->previewTheme()) {
                $this->items[] = 'Theme: '.$theme->name;
            }

            $drafts = array_keys($resolver->previewDrafts());

            if ($drafts) {
                $this->items[] = count($drafts) === 1
                    ? 'Template: '.$drafts[0]
                    : count($drafts).' Draft Templates';
            }
        } catch (\Throwable $e) {
            $this->items = [];
        }
    }

    public function render()
    {
        return view('components.preview-badge');
    }
}
