<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\StorePage;

class PageController extends Controller
{
    /** A single published policy/info page (shipping, refund-policy, terms, privacy, ...). */
    public function show(StorePage $page)
    {
        abort_unless($page->is_published, 404);

        return view('shop.pages.show', [
            'page' => $page,
        ]);
    }
}
