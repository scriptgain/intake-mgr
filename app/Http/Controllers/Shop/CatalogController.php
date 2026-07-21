<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Product;
use App\Services\SeoService;
use App\Support\Money;
use Illuminate\Http\Request;

/**
 * The public catalog: home, browse, collection pages, product detail, search.
 *
 * Every figure a template prints is prepared here (formatted prices, option
 * axes, the variant lookup map). Blade stays markup.
 */
class CatalogController extends Controller
{
    public function __construct(private SeoService $seo) {}

    public function home()
    {
        $this->seo->home();

        return view('shop.home', [
            'featured' => Product::storefront()
                ->with(['variants', 'images'])
                ->where('is_featured', true)
                ->orderBy('position')
                ->limit(8)
                ->get(),
            'newest' => Product::storefront()
                ->with(['variants', 'images'])
                ->latest('id')
                ->limit(8)
                ->get(),
            'collections' => Collection::active()
                ->withCount('products')
                ->orderBy('position')
                ->limit(3)
                ->get(),
        ]);
    }

    public function index(Request $request)
    {
        $products = $this->query($request)->paginate($this->perPage())->withQueryString();

        $this->seo->catalog($products);

        return view('shop.catalog', [
            'heading' => 'All Products',
            'subheading' => null,
            'collection' => null,
            'products' => $products,
            'collections' => Collection::active()->orderBy('position')->get(),
            'sortOptions' => $this->sortOptions(),
            'filters' => $request->only(['sort', 'q', 'min', 'max']),
        ]);
    }

    public function collection(Request $request, Collection $collection)
    {
        abort_unless($collection->is_active, 404);

        $products = $this->query($request)
            ->whereHas('collections', fn ($q) => $q->where('collections.id', $collection->id))
            ->paginate($this->perPage())
            ->withQueryString();

        $this->seo->collection($collection, $products);

        return view('shop.catalog', [
            'heading' => $collection->name,
            'subheading' => $collection->description,
            'collection' => $collection,
            'products' => $products,
            'collections' => Collection::active()->orderBy('position')->get(),
            'sortOptions' => $this->sortOptions(),
            'filters' => $request->only(['sort', 'q', 'min', 'max']),
        ]);
    }

    public function collections()
    {
        $this->seo->collectionsIndex();

        return view('shop.collections', [
            'collections' => Collection::active()
                ->withCount('products')
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function search(Request $request)
    {
        $term = $request->string('q')->toString();

        $this->seo->search($term ?: null);

        return view('shop.catalog', [
            'heading' => $term ? 'Results For "'.$term.'"' : 'Search',
            'subheading' => null,
            'collection' => null,
            'products' => $this->query($request)->paginate($this->perPage())->withQueryString(),
            'collections' => Collection::active()->orderBy('position')->get(),
            'sortOptions' => $this->sortOptions(),
            'filters' => $request->only(['sort', 'q', 'min', 'max']),
        ]);
    }

    public function product(Product $product)
    {
        abort_unless($product->status === 'active', 404);

        $product->load(['variants', 'images', 'collections']);

        $this->seo->product($product);

        return view('shop.product', [
            'product' => $product,
            // The picker needs a value-combination -> variant map. Building it
            // here keeps the Alpine component a lookup and the Blade markup.
            'variantMap' => $product->variants->mapWithKeys(fn ($v) => [
                implode('|', $v->option_values) ?: 'default' => [
                    'id' => $v->id,
                    'price' => Money::format($v->price_cents),
                    'compare_at' => $v->compare_at_formatted,
                    'in_stock' => $v->is_in_stock,
                    'low_stock' => $v->is_low_stock,
                    'qty' => $v->purchasableQuantity(),
                    'sku' => $v->sku,
                ],
            ])->all(),
            'optionAxes' => $product->option_axes,
            'defaultVariant' => $product->defaultVariant(),
            'related' => Product::storefront()
                ->with(['variants', 'images'])
                ->whereHas('collections', fn ($q) => $q->whereIn('collections.id', $product->collections->pluck('id')))
                ->where('id', '!=', $product->id)
                ->limit(4)
                ->get(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function query(Request $request)
    {
        $query = Product::storefront()
            ->with(['variants', 'images'])
            ->search($request->string('q')->toString() ?: null);

        // Price filters compare against the cheapest variant, which is what the
        // card shows, so a filtered grid matches the prices on screen.
        if ($request->filled('min')) {
            $min = Money::parse($request->string('min')->toString());
            $query->whereHas('variants', fn ($q) => $q->where('price_cents', '>=', $min));
        }
        if ($request->filled('max')) {
            $max = Money::parse($request->string('max')->toString());
            $query->whereHas('variants', fn ($q) => $q->where('price_cents', '<=', $max));
        }

        return match ($request->string('sort')->toString()) {
            'price-asc' => $query->withMin('variants', 'price_cents')->orderBy('variants_min_price_cents'),
            'price-desc' => $query->withMin('variants', 'price_cents')->orderByDesc('variants_min_price_cents'),
            'name' => $query->orderBy('name'),
            'oldest' => $query->oldest('id'),
            default => $query->latest('id'),
        };
    }

    private function sortOptions(): array
    {
        return [
            'newest' => 'Newest',
            'oldest' => 'Oldest',
            'price-asc' => 'Price: Low To High',
            'price-desc' => 'Price: High To Low',
            'name' => 'Name: A To Z',
        ];
    }

    private function perPage(): int
    {
        return (int) config('shop.products_per_page', 12);
    }
}
