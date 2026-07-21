<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.collections.index', [
            'collections' => Collection::withCount('products')
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
        return view('admin.collections.create', [
            'collection' => new Collection(['is_active' => true]),
            'products' => Product::orderBy('name')->get(),
            'selectedProducts' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $collection = Collection::create($data['collection']);
        $collection->products()->sync($data['products']);

        return redirect()->route('collections.edit', $collection)->with('status', 'Collection created.');
    }

    public function show(Collection $collection)
    {
        return redirect()->route('collections.edit', $collection);
    }

    public function edit(Collection $collection)
    {
        return view('admin.collections.edit', [
            'collection' => $collection,
            'products' => Product::orderBy('name')->get(),
            'selectedProducts' => $collection->products->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Collection $collection)
    {
        $data = $this->validated($request, $collection);
        $collection->update($data['collection']);
        $collection->products()->sync($data['products']);

        return back()->with('status', 'Collection saved.');
    }

    public function destroy(Collection $collection)
    {
        if ($collection->image_path) {
            Storage::disk('public')->delete($collection->image_path);
        }

        $collection->delete();

        return redirect()->route('collections.index')->with('status', 'Collection deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = Collection::whereIn('id', $ids)->delete();

        return back()->with('status', "Deleted {$count} collection(s).");
    }

    private function validated(Request $request, ?Collection $collection = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('collections', 'slug')->ignore($collection?->id)],
            'description' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'url', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:500'],
            'image' => ['nullable', 'image', 'max:8192'],
            'products' => ['nullable', 'array'],
            'products.*' => ['integer', 'exists:products,id'],
        ]);

        $imagePath = $collection?->image_path;
        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('collections', 'public');
        }

        return [
            'collection' => [
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? null,
                'description' => $validated['description'] ?? null,
                'position' => (int) ($validated['position'] ?? 0),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'og_image' => $validated['og_image'] ?? null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'noindex' => $request->boolean('noindex'),
                'image_path' => $imagePath,
                'is_active' => $request->boolean('is_active'),
            ],
            'products' => $validated['products'] ?? [],
        ];
    }
}
