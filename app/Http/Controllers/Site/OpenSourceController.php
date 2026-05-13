<?php

namespace App\Http\Controllers\Site;

use App\Models\Project;
use Illuminate\Contracts\View\View;

class OpenSourceController
{
    public function index(): View
    {
        $topRepos = Project::query()
            ->published()
            ->orderByDesc('stars')
            ->take(6)
            ->get();

        return view('site.open-source', [
            'osStats'  => config('site.os_stats'),
            'topRepos' => $topRepos,
        ]);
    }
}
