<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HelpArticleController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.help-articles.index', [
            'articles' => HelpArticle::with('category')
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
                ->when($request->filled('category'), fn ($q) => $q->where('help_category_id', (int) $request->input('category')))
                ->orderBy('position')
                ->orderBy('title')
                ->paginate((int) config('shop.rows_per_page', 25))
                ->withQueryString(),
            'categories' => HelpCategory::orderBy('name')->get(),
            'filters' => $request->only('q', 'category'),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.help-articles.create', [
            'article' => new HelpArticle([
                'is_published' => true,
                'help_category_id' => (int) $request->input('category') ?: null,
            ]),
            'categories' => HelpCategory::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $article = HelpArticle::create($this->validated($request));

        return redirect()->route('help-articles.edit', $article)->with('status', 'Article created.');
    }

    public function edit(HelpArticle $helpArticle)
    {
        return view('admin.help-articles.edit', [
            'article' => $helpArticle,
            'categories' => HelpCategory::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, HelpArticle $helpArticle)
    {
        $helpArticle->update($this->validated($request, $helpArticle));

        return back()->with('status', 'Article saved.');
    }

    public function destroy(HelpArticle $helpArticle)
    {
        $helpArticle->delete();

        return redirect()->route('help-articles.index')->with('status', 'Article deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = 0;
        foreach (HelpArticle::whereIn('id', $ids)->get() as $article) {
            $article->delete();
            $count++;
        }

        return back()->with('status', "Deleted {$count} article(s).");
    }

    private function validated(Request $request, ?HelpArticle $article = null): array
    {
        $validated = $request->validate([
            'help_category_id' => ['required', 'integer', 'exists:help_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('help_articles', 'slug')->ignore($article?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'help_category_id' => (int) $validated['help_category_id'],
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'] ?? null,
            'position' => (int) ($validated['position'] ?? 0),
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
