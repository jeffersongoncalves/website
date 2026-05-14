<?php

namespace App\Http\Controllers\Site;

use Illuminate\Contracts\View\View;

class AboutController
{
    public function __invoke(): View
    {
        return view('site.about', [
            'timeline' => config('site.timeline'),
            'education' => config('site.education'),
            'principles' => config('site.principles'),
        ]);
    }
}
