<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['variants', 'images', 'collections'])
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('collection'), fn ($q) => $q->whereHas(
                'collections',
                fn ($c) => $c->where('collections.id', $request->integer('collection'))
            ))
            ->orderBy('position')
            ->latest('id')
            ->paginate((int) config('shop.rows_per_page', 25))
            ->withQueryString();

        $filters = $request->only(['q', 'status', 'collection']);

        $statusCounts = [
            'all' => Product::count(),
            'active' => Product::where('status', 'active')->count(),
            'draft' => Product::where('status', 'draft')->count(),
            'archived' => Product::where('status', 'archived')->count(),
        ];

        return view('admin.products.index', [
            'products' => $products,
            'collections' => Collection::orderBy('name')->get(),
            'filters' => $filters,
            'statusCounts' => $statusCounts,
            'tabs' => $this->indexTabs($filters, $statusCounts),
            'hasFilters' => (bool) array_filter($filters),
        ]);
    }

    /** Status tabs, each resolved to its URL and active state. */
    private function indexTabs(array $filters, array $counts): array
    {
        $labels = ['' => 'All', 'active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'];
        $current = $filters['status'] ?? '';

        $tabs = [];
        foreach ($labels as $value => $label) {
            $tabs[] = [
                'label' => $label,
                'count' => $counts[$value === '' ? 'all' : $value] ?? 0,
                'active' => $current === $value,
                'href' => route('products.index', array_filter(array_merge($filters, ['status' => $value]))),
            ];
        }

        return $tabs;
    }

    public function create()
    {
        return view('admin.products.create', [
            'product' => new Product(['status' => 'draft', 'requires_shipping' => true]),
            'collections' => Collection::orderBy('name')->get(),
            'selectedCollections' => [],
            'initialVariants' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data['product']);
            $product->collections()->sync($data['collections']);
            $this->syncVariants($product, $data['variants']);

            return $product;
        });

        return redirect()->route('products.edit', $product)
            ->with('status', 'Product created.');
    }

    public function show(Product $product)
    {
        // The admin has no separate read-only product page; editing is the view.
        return redirect()->route('products.edit', $product);
    }

    public function edit(Product $product)
    {
        $product->load(['variants', 'images', 'collections']);

        return view('admin.products.edit', [
            'product' => $product,
            'collections' => Collection::orderBy('name')->get(),
            'selectedCollections' => $product->collections->pluck('id')->all(),
            'initialVariants' => $this->variantPayload($product),
        ]);
    }

    /**
     * Existing variants, shaped for the JS repeater in public/js/shop-admin.js.
     * Money is pre-formatted to the plain string the controller expects back,
     * since syncVariants() parses it via Money::parse().
     */
    private function variantPayload(Product $product): array
    {
        return $product->variants->map(fn (ProductVariant $variant) => [
            'id' => $variant->id,
            'option1_name' => $variant->option1_name,
            'option1_value' => $variant->option1_value,
            'option2_name' => $variant->option2_name,
            'option2_value' => $variant->option2_value,
            'option3_name' => $variant->option3_name,
            'option3_value' => $variant->option3_value,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => $variant->price_input,
            'compare_at_price' => $variant->compare_at_input,
            'cost' => $variant->cost_input,
            'inventory_qty' => $variant->inventory_qty,
            'weight_grams' => $variant->weight_grams,
            'track_inventory' => $variant->track_inventory,
        ])->values()->all();
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);

        DB::transaction(function () use ($product, $data) {
            $product->update($data['product']);
            $product->collections()->sync($data['collections']);
            $this->syncVariants($product, $data['variants']);
        });

        return back()->with('status', 'Product saved.');
    }

    public function destroy(Product $product)
    {
        $product->delete(); // soft delete: order history keeps its reference

        return redirect()->route('products.index')->with('status', 'Product deleted.');
    }

    /** massSelect bulk delete, driven by the confirm modal on the index table. */
    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = Product::whereIn('id', $ids)->get()->each->delete()->count();

        return back()->with('status', "Deleted {$count} product(s).");
    }

    /** Bulk publish/unpublish from the same selection toolbar. */
    public function bulkStatus(Request $request)
    {
        $request->validate(['status' => ['required', Rule::in(Product::STATUSES)]]);

        $ids = collect($request->input('ids', []))->filter()->all();
        $count = Product::whereIn('id', $ids)->update(['status' => $request->string('status')]);

        return back()->with('status', "Updated {$count} product(s).");
    }

    /** Clone a product with its variants — the fastest way to add a similar SKU. */
    public function duplicate(Product $product)
    {
        $copy = DB::transaction(function () use ($product) {
            $copy = $product->replicate(['slug']);
            $copy->name = $product->name.' (Copy)';
            $copy->slug = Product::uniqueSlug($copy->name);
            $copy->status = 'draft';
            $copy->save();

            $copy->collections()->sync($product->collections->pluck('id'));

            foreach ($product->variants as $variant) {
                $new = $variant->replicate(['sku']);
                $new->product_id = $copy->id;
                // SKUs are unique; a copy cannot inherit one.
                $new->sku = null;
                $new->save();
            }

            return $copy;
        });

        return redirect()->route('products.edit', $copy)->with('status', 'Product duplicated.');
    }

    public function storeImage(Request $request, Product $product)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $request->file('image')->store('products/'.$product->id, 'public');

        $product->images()->create([
            'path' => $path,
            'alt' => $request->string('alt')->toString() ?: null,
            'position' => (int) $product->images()->max('position') + 1,
        ]);

        return back()->with('status', 'Image uploaded.');
    }

    public function destroyImage(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('status', 'Image removed.');
    }

    /** Quick inventory edit from the product page's Inventory tab. */
    public function updateInventory(Request $request, Product $product)
    {
        $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:-99999'],
        ]);

        foreach ($request->input('quantities', []) as $variantId => $qty) {
            $product->variants()
                ->where('id', $variantId)
                ->update(['inventory_qty' => (int) $qty]);
        }

        return back()->with('status', 'Inventory updated.');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Validate the product form. Prices arrive as human strings ("19.99") and
     * are converted to integer cents here — the money layer never sees a float.
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Product::STATUSES)],
            'vendor' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:255'],
            'tax_class' => ['nullable', 'string', 'max:64'],
            // Per-entity SEO. The legacy seo_title/seo_description columns are
            // no longer posted by the form; they are left on the record
            // untouched as a read fallback.
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'url', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:500'],
            'collections' => ['nullable', 'array'],
            'collections.*' => ['integer', 'exists:collections,id'],

            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.option1_name' => ['nullable', 'string', 'max:64'],
            'variants.*.option1_value' => ['nullable', 'string', 'max:64'],
            'variants.*.option2_name' => ['nullable', 'string', 'max:64'],
            'variants.*.option2_value' => ['nullable', 'string', 'max:64'],
            'variants.*.option3_name' => ['nullable', 'string', 'max:64'],
            'variants.*.option3_value' => ['nullable', 'string', 'max:64'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['required', 'string'],
            'variants.*.compare_at_price' => ['nullable', 'string'],
            'variants.*.cost' => ['nullable', 'string'],
            'variants.*.inventory_qty' => ['nullable', 'integer'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'product' => [
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? null,
                'excerpt' => $validated['excerpt'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'vendor' => $validated['vendor'] ?? null,
                'product_type' => $validated['product_type'] ?? null,
                'tax_class' => $validated['tax_class'] ?: 'standard',
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'og_image' => $validated['og_image'] ?? null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'noindex' => $request->boolean('noindex'),
                // Toggle switches post 1/0 through a hidden input.
                'requires_shipping' => $request->boolean('requires_shipping'),
                'is_featured' => $request->boolean('is_featured'),
            ],
            'collections' => $validated['collections'] ?? [],
            'variants' => $validated['variants'],
        ];
    }

    /**
     * Create/update/delete variants to match the submitted rows. Variants absent
     * from the submission are removed, so the form is the source of truth.
     */
    private function syncVariants(Product $product, array $rows): void
    {
        $keptIds = [];

        foreach (array_values($rows) as $position => $row) {
            $attributes = [
                'option1_name' => $row['option1_name'] ?? null,
                'option1_value' => $row['option1_value'] ?? null,
                'option2_name' => $row['option2_name'] ?? null,
                'option2_value' => $row['option2_value'] ?? null,
                'option3_name' => $row['option3_name'] ?? null,
                'option3_value' => $row['option3_value'] ?? null,
                'sku' => $row['sku'] ?: null,
                'barcode' => $row['barcode'] ?? null,
                'price_cents' => Money::parse($row['price']) ?? 0,
                'compare_at_price_cents' => Money::parse($row['compare_at_price'] ?? null),
                'cost_cents' => Money::parse($row['cost'] ?? null),
                'inventory_qty' => (int) ($row['inventory_qty'] ?? 0),
                'weight_grams' => (int) ($row['weight_grams'] ?? 0),
                'track_inventory' => ! empty($row['track_inventory']),
                'position' => $position,
                'is_default' => $position === 0,
            ];

            if (! empty($row['id']) && $variant = $product->variants()->find($row['id'])) {
                $variant->update($attributes);
                $keptIds[] = $variant->id;
            } else {
                $keptIds[] = $product->variants()->create($attributes)->id;
            }
        }

        $product->variants()->whereNotIn('id', $keptIds)->delete();
    }
}
