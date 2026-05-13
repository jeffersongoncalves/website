<?php

namespace App\Http\Controllers\Site;

use Illuminate\Contracts\View\View;

class AboutController
{
    public function index(): View
    {
        return view('site.about', [
            'timeline'   => config('site.timeline'),
            'education'  => config('site.education'),
            'principles' => config('site.principles'),
        ]);
    }
}
