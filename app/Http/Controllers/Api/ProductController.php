<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Product::with(['variants', 'images', 'collections'])
                ->search($request->query('q'))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->paginate((int) $request->query('per_page', 25))
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $product = Product::create($data);

        // A product is only usable once it has a variant to carry the price.
        $product->variants()->create([
            'price_cents' => Money::parse($request->input('price')) ?? 0,
            'sku' => $request->input('sku'),
            'inventory_qty' => (int) $request->input('inventory_qty', 0),
            'is_default' => true,
        ]);

        return response()->json($product->load('variants'), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['variants', 'images', 'collections']));
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));

        return response()->json($product->load('variants'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => [$product ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(Product::STATUSES)],
            'vendor' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:255'],
            'tax_class' => ['nullable', 'string', 'max:64'],
            'requires_shipping' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ]);
    }
}
