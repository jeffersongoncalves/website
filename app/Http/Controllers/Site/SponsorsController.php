<?php

namespace App\Http\Controllers\Site;

use Illuminate\Contracts\View\View;

class SponsorsController
{
    public function index(): View
    {
        return view('site.sponsors');
    }
}
