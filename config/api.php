<?php

/*
|--------------------------------------------------------------------------
| Merchant REST API
|--------------------------------------------------------------------------
| Knobs for the bearer-token API at /api/v1. The base URL is the install's
| own APP_URL, so each deployment documents itself at /docs.
*/

return [
    'version' => 'v1',

    // Pagination. per_page defaults to `default` and is capped at `max`.
    'per_page' => 25,
    'max_per_page' => 100,

    // Requests per minute per token (or per IP when unauthenticated).
    'rate_limit' => 120,
];
