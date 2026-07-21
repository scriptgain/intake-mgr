<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HelpCategoryController extends Controller
{
    /** Icons offered in the category form, drawn from the shared x-icon set. */
    private const ICON_CHOICES = [
        'book', 'folder', 'truck', 'credit-card', 'tag', 'box', 'info', 'shield',
        'lock', 'key', 'refresh', 'bell', 'envelope', 'globe', 'users', 'percent', 'star', 'bag',
    ];

    public function index(Request $request)
    {
        return view('admin.help-categories.index', [
            'categories' => HelpCategory::withCount('articles')
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
                ->orderBy('position')
                ->orderBy('name')
                ->paginate((int) config('shop.rows_per_page', 25))
                ->withQueryString(),
            'filters' => $request->only('q'),
        ]);
    }

    public function create()
    {
        return view('admin.help-categories.create', [
            'category' => new HelpCategory(['is_published' => true, 'icon' => 'book']),
            'iconChoices' => self::ICON_CHOICES,
        ]);
    }

    public function store(Request $request)
    {
        $category = HelpCategory::create($this->validated($request));

        return redirect()->route('help-categories.edit', $category)->with('status', 'Category created.');
    }

    public function edit(HelpCategory $helpCategory)
    {
        return view('admin.help-categories.edit', [
            'category' => $helpCategory->loadCount('articles'),
            'iconChoices' => self::ICON_CHOICES,
        ]);
    }

    public function update(Request $request, HelpCategory $helpCategory)
    {
        $helpCategory->update($this->validated($request, $helpCategory));

        return back()->with('status', 'Category saved.');
    }

    public function destroy(HelpCategory $helpCategory)
    {
        $helpCategory->delete();

        return redirect()->route('help-categories.index')->with('status', 'Category deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = 0;
        // Delete through the model so cascade + audit entries still fire.
        foreach (HelpCategory::whereIn('id', $ids)->get() as $category) {
            $category->delete();
            $count++;
        }

        return back()->with('status', "Deleted {$count} category(s).");
    }

    private function validated(Request $request, ?HelpCategory $category = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('help_categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?: 'book',
            'position' => (int) ($validated['position'] ?? 0),
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
