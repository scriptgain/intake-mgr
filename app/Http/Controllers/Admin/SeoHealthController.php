<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Product;
use App\Services\SeoService;
use Illuminate\Support\Str;

/**
 * SEO health: an exception list, not a score.
 *
 * The same Ledger reading as the rest of the admin. Nothing that is fine is
 * rendered. Every row is a specific product with a specific defect and a link
 * straight to the tab that fixes it, and the summary strip at the top is a
 * worklist of counts rather than a gauge.
 *
 * Note the distinction the screen draws throughout: a product with no
 * meta_title is not broken, because the resolver falls back to the product
 * name. It is *unoptimised*, which is a different tone (amber) from a genuine
 * indexing fault like a duplicate title across two live products (rose).
 */
class SeoHealthController extends Controller
{
    public function __construct(private SeoService $seo) {}

    public function index()
    {
        $products = Product::with(['images', 'variants', 'collections'])
            ->whereIn('status', ['active', 'draft'])
            ->orderBy('name')
            ->get();

        $rows = $products->map(fn (Product $product) => $this->row($product));

        $duplicateTitles = $this->duplicates($rows, 'resolved_title');
        $duplicateDescriptions = $this->duplicates($rows, 'resolved_description');

        $groups = [
            'missing-title' => [
                'label' => 'No Meta Title',
                'tone' => 'warn',
                'icon' => 'edit',
                'action' => 'Write Titles',
                'blurb' => 'These fall back to the product name. A written title outperforms the name on nearly every commercial query.',
                'rows' => $rows->where('missing_title', true)->values(),
            ],
            'missing-description' => [
                'label' => 'No Meta Description',
                'tone' => 'warn',
                'icon' => 'book',
                'action' => 'Write Descriptions',
                'blurb' => 'Without one the search engine writes the snippet for you, and it usually picks the navigation.',
                'rows' => $rows->where('missing_description', true)->values(),
            ],
            'duplicate-title' => [
                'label' => 'Duplicate Title',
                'tone' => 'danger',
                'icon' => 'copy',
                'action' => 'Make Unique',
                'blurb' => 'Two live pages competing on the same title is the single clearest duplicate-content signal a shop can send.',
                'rows' => $rows->whereIn('resolved_title_key', $duplicateTitles)->values(),
            ],
            'duplicate-description' => [
                'label' => 'Duplicate Description',
                'tone' => 'danger',
                'icon' => 'copy',
                'action' => 'Make Unique',
                'blurb' => 'Repeated snippets across a catalogue read as boilerplate and get rewritten or dropped.',
                'rows' => $rows->whereIn('resolved_description_key', $duplicateDescriptions)->values(),
            ],
            'length' => [
                'label' => 'Length Problems',
                'tone' => 'warn',
                'icon' => 'filter',
                'action' => 'Trim Or Expand',
                'blurb' => 'Titles over '.config('seo.title_max').' characters and descriptions over '.config('seo.description_max').' get truncated in results.',
                'rows' => $rows->where('has_length_issue', true)->values(),
            ],
            'no-image' => [
                'label' => 'No Image',
                'tone' => 'danger',
                'icon' => 'folder',
                'action' => 'Upload Images',
                'blurb' => 'No image means no product rich result and no link preview. It is the most expensive gap on this page.',
                'rows' => $rows->where('has_image', false)->values(),
            ],
            'noindex' => [
                'label' => 'Excluded From Search',
                'tone' => 'info',
                'icon' => 'eye',
                'action' => 'Review',
                'blurb' => 'Deliberately hidden from search engines and left out of the sitemap. Listed so it is never a surprise.',
                'rows' => $rows->where('noindex', true)->values(),
            ],
        ];

        $groups = array_filter($groups, fn (array $group) => $group['rows']->isNotEmpty());

        return view('admin.seo.index', [
            'groups' => $groups,
            'tabs' => $this->tabs($groups),
            'worklist' => $this->worklist($groups),
            'totalProducts' => $products->count(),
            'cleanCount' => $rows->where('is_clean', true)->count(),
            'collectionIssues' => $this->collectionIssues(),
            'sitemapUrl' => route('sitemap.index'),
            'robotsUrl' => route('robots'),
            'siteNoindex' => (bool) config('seo.site_noindex'),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** One product, with every check already answered. */
    private function row(Product $product): array
    {
        $title = $this->seo->autoTitle($product);
        $description = $this->seo->autoDescription($product);

        $missingTitle = blank($product->meta_title) && blank($product->seo_title);
        $missingDescription = blank($product->meta_description)
            && blank($product->seo_description)
            && blank($product->excerpt)
            && blank($product->description);

        $titleLength = Str::length($title);
        $descriptionLength = Str::length($description);

        $titleIssue = $this->lengthIssue($titleLength, (int) config('seo.title_min'), (int) config('seo.title_max'));
        $descriptionIssue = $this->lengthIssue($descriptionLength, (int) config('seo.description_min'), (int) config('seo.description_max'));

        return [
            'id' => $product->id,
            'name' => $product->name,
            'status' => $product->status,
            'status_badge' => $product->status_badge,
            'edit_url' => route('products.edit', $product),
            'view_url' => route('shop.product', $product->slug),
            'image_url' => $product->primaryImage()?->url,
            'has_image' => $product->images->isNotEmpty(),
            'noindex' => (bool) $product->noindex,

            'resolved_title' => $title,
            'resolved_description' => $description,
            'resolved_title_key' => Str::lower($title),
            'resolved_description_key' => Str::lower($description),

            'missing_title' => $missingTitle,
            'missing_description' => $missingDescription,

            'title_length' => $titleLength,
            'description_length' => $descriptionLength,
            'title_issue' => $titleIssue,
            'description_issue' => $descriptionIssue,
            'has_length_issue' => $titleIssue !== null || $descriptionIssue !== null,

            'is_clean' => ! $missingTitle
                && ! $missingDescription
                && $titleIssue === null
                && $descriptionIssue === null
                && $product->images->isNotEmpty()
                && ! $product->noindex,
        ];
    }

    private function lengthIssue(int $length, int $min, int $max): ?string
    {
        if ($length === 0) {
            return 'Empty';
        }
        if ($length > $max) {
            return 'Too Long ('.$length.')';
        }
        if ($length < $min) {
            return 'Too Short ('.$length.')';
        }

        return null;
    }

    /** Values that appear on more than one product. */
    private function duplicates(\Illuminate\Support\Collection $rows, string $field): array
    {
        return $rows
            ->pluck($field)
            ->map(fn ($value) => Str::lower((string) $value))
            ->filter()
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->keys()
            ->all();
    }

    /** Segmented tabs, one per issue group that has rows. */
    private function tabs(array $groups): array
    {
        return array_map(fn (string $key, array $group) => [
            'key' => $key,
            'label' => $group['label'],
            'count' => $group['rows']->count(),
        ], array_keys($groups), $groups);
    }

    /** The Needs Attention strip: same shape as the dashboard's worklist. */
    private function worklist(array $groups): array
    {
        return array_map(fn (string $key, array $group) => [
            'key' => $key,
            'count' => $group['rows']->count(),
            'label' => $group['label'],
            'action' => $group['action'],
            'tone' => $group['tone'],
            'icon' => $group['icon'],
        ], array_keys($groups), $groups);
    }

    /** Collections get the same two checks, on a much smaller table. */
    private function collectionIssues(): array
    {
        return Collection::orderBy('name')->get()
            ->map(function (Collection $collection) {
                $title = $this->seo->autoTitle($collection);
                $description = $this->seo->autoDescription($collection);

                return [
                    'name' => $collection->name,
                    'edit_url' => route('collections.edit', $collection),
                    'missing_title' => blank($collection->meta_title) && blank($collection->seo_title),
                    'missing_description' => blank($collection->meta_description)
                        && blank($collection->seo_description)
                        && blank($collection->description),
                    'noindex' => (bool) $collection->noindex,
                    'title_length' => Str::length($title),
                    'description_length' => Str::length($description),
                ];
            })
            ->filter(fn (array $row) => $row['missing_title'] || $row['missing_description'] || $row['noindex'])
            ->values()
            ->all();
    }
}
