<?php

namespace App\Http\Controllers\Site;

use App\Models\Project;
use App\Support\SiteStats;
use Illuminate\Contracts\View\View;

class HomeController
{
    public function __invoke(): View
    {
        $featured = Project::query()
            ->published()
            ->featured()
            ->orderByDesc('stars')
            ->take(6)
            ->get();

        return view('site.home', [
            'featured' => $featured,
            'homeStats' => SiteStats::homeCards(),
            'stack' => config('site.stack'),
            'contributions' => SiteStats::contributions(),
        ]);
    }
}
