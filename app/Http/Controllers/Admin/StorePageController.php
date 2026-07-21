<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StorePage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorePageController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.store-pages.index', [
            'pages' => StorePage::when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
                ->orderBy('position')
                ->orderBy('title')
                ->paginate((int) config('shop.rows_per_page', 25))
                ->withQueryString(),
            'filters' => $request->only('q'),
        ]);
    }

    public function create()
    {
        return view('admin.store-pages.create', [
            'page' => new StorePage(['is_published' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $page = StorePage::create($this->validated($request));

        return redirect()->route('store-pages.edit', $page)->with('status', 'Page created.');
    }

    public function edit(StorePage $storePage)
    {
        return view('admin.store-pages.edit', [
            'page' => $storePage,
        ]);
    }

    public function update(Request $request, StorePage $storePage)
    {
        $storePage->update($this->validated($request, $storePage));

        return back()->with('status', 'Page saved.');
    }

    public function destroy(StorePage $storePage)
    {
        $storePage->delete();

        return redirect()->route('store-pages.index')->with('status', 'Page deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = 0;
        foreach (StorePage::whereIn('id', $ids)->get() as $page) {
            $page->delete();
            $count++;
        }

        return back()->with('status', "Deleted {$count} page(s).");
    }

    private function validated(Request $request, ?StorePage $page = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('store_pages', 'slug')->ignore($page?->id)],
            'body' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?? null,
            'body' => $validated['body'] ?? null,
            'position' => (int) ($validated['position'] ?? 0),
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
