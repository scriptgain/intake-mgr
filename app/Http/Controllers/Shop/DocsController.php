<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Support\ApiSpec;

/**
 * Public API reference for this install. Both outputs come from ApiSpec so the
 * human page and the machine spec stay in lockstep.
 */
class DocsController extends Controller
{
    public function index()
    {
        return view('shop.docs', [
            'meta' => ApiSpec::meta(),
            'groups' => ApiSpec::groups(),
        ]);
    }

    /** OpenAPI 3.0 document generated from ApiSpec. */
    public function openapi()
    {
        $meta = ApiSpec::meta();
        $paths = [];

        foreach (ApiSpec::groups() as $group) {
            foreach ($group['endpoints'] as $ep) {
                [$method, $path, $summary] = [$ep[0], $ep[1], $ep[2]];
                $params = $ep[3] ?? [];
                $body = $ep[4] ?? null;

                $op = [
                    'tags' => [$group['label']],
                    'summary' => $summary,
                    'security' => [['bearerAuth' => []]],
                    'responses' => ['200' => ['description' => 'Successful response']],
                ];

                // Path parameters from {placeholders}.
                $parameters = [];
                if (preg_match_all('/\{(\w+)\}/', $path, $m)) {
                    foreach ($m[1] as $name) {
                        $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
                    }
                }
                foreach ($params as $p) {
                    $parameters[] = ['name' => $p['name'], 'in' => $p['in'] ?? 'query', 'required' => false, 'description' => $p['desc'] ?? '', 'schema' => ['type' => 'string']];
                }
                if ($parameters) {
                    $op['parameters'] = $parameters;
                }

                if ($body) {
                    $op['requestBody'] = [
                        'content' => ['application/json' => [
                            'schema' => ['type' => 'object', 'description' => 'Fields: '.implode(', ', $body)],
                        ]],
                    ];
                }

                $paths[$path][strtolower($method)] = $op;
            }
        }

        $doc = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $meta['title'],
                'version' => $meta['version'],
                'description' => 'Bearer-token REST API for the '.config('brand.name', 'IntakeMGR').' service desk. Rate limit '.$meta['rate_limit'].' requests/minute per token. List endpoints paginate ('.$meta['per_page'].' per page by default, max '.$meta['max_per_page'].').',
            ],
            'servers' => [['url' => $meta['base_url']]],
            'security' => [['bearerAuth' => []]],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'An API token (vlt_...) minted in Admin, Settings, API Tokens.'],
                ],
            ],
            'paths' => $paths,
        ];

        return response()->json($doc, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
