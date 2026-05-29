<?php

namespace App\Http\Controllers\Site;

use App\Models\Project;
use App\Support\SiteStats;
use Illuminate\Contracts\View\View;

class OpenSourceController
{
    public function __invoke(): View
    {
        $topRepos = Project::query()
            ->published()
            ->orderByDesc('stars')
            ->take(6)
            ->get();

        return view('site.open-source', [
            'osStats' => SiteStats::osCards(),
            'topRepos' => $topRepos,
            'contributions' => SiteStats::contributions(),
            'reposCount' => SiteStats::all()['repos'],
        ]);
    }
}
