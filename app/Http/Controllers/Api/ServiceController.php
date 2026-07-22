<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Read-only catalog of bookable/quotable services (Products). Only active,
 * published services are listed; variants are eager-loaded so the price
 * accessors resolve.
 */
class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Product::with('variants')
            ->active()
            ->search($request->query('q'))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ServiceResource::collection($services);
    }

    public function show(Product $service)
    {
        return new ServiceResource($service->load('variants'));
    }

    private function perPage(Request $request): int
    {
        return min(
            max(1, (int) $request->query('per_page', config('api.per_page', 25))),
            (int) config('api.max_per_page', 100)
        );
    }
}
