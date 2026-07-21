<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\Request;

class HelpController extends Controller
{
    /** Help Center landing: published categories with their published articles. */
    public function index()
    {
        $categories = HelpCategory::published()
            ->with('publishedArticles')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('shop.help.index', [
            'categories' => $categories,
        ]);
    }

    /** A single category and every published article inside it. */
    public function category(HelpCategory $category)
    {
        abort_unless($category->is_published, 404);

        $category->load('publishedArticles');

        return view('shop.help.category', [
            'category' => $category,
        ]);
    }

    /**
     * A single article. Route model binding is scoped so the article must
     * belong to the category in the path; both must be published.
     */
    public function article(HelpCategory $category, HelpArticle $article)
    {
        abort_unless($category->is_published && $article->is_published, 404);

        // Fire-and-forget view counter; never blocks the render.
        $article->increment('views');

        $related = $category->publishedArticles()
            ->whereKeyNot($article->id)
            ->limit(6)
            ->get();

        return view('shop.help.article', [
            'category' => $category,
            'article' => $article,
            'related' => $related,
        ]);
    }

    /** Full-text-ish search across published article titles, excerpts and bodies. */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        $results = collect();
        if ($term !== '') {
            $results = HelpArticle::published()
                ->with('category')
                ->whereHas('category', fn ($q) => $q->where('is_published', true))
                ->where(function ($q) use ($term) {
                    $like = '%'.$term.'%';
                    $q->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhere('body', 'like', $like);
                })
                ->orderBy('title')
                ->limit(50)
                ->get();
        }

        return view('shop.help.search', [
            'term' => $term,
            'results' => $results,
        ]);
    }
}
