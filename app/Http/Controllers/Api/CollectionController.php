<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Collection::withCount('products')->paginate((int) $request->query('per_page', 25))
        );
    }

    public function store(Request $request)
    {
        return response()->json(Collection::create($this->validated($request)), 201);
    }

    public function show(Collection $collection)
    {
        return response()->json($collection->load('products'));
    }

    public function update(Request $request, Collection $collection)
    {
        $collection->update($this->validated($request, $collection));

        return response()->json($collection);
    }

    public function destroy(Collection $collection)
    {
        $collection->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Collection $collection = null): array
    {
        return $request->validate([
            'name' => [$collection ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('collections', 'slug')->ignore($collection?->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
