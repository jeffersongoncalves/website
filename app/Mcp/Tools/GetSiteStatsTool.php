<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Support\SiteStats;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Aggregate portfolio stats — the same cached SiteStat row (App\Support\
 * SiteStats::all(), written by the scheduled projects:sync-metrics command)
 * that backs the site's own "About" counters. No arguments, no query: lets a
 * client answer "how many packages/stars/downloads" without paginating
 * search_projects across dozens of rows.
 */
#[Description('Aggregate stats for Jefferson Gonçalves\' open-source work: repo/package counts by category, total stars and downloads by registry, GitHub followers and sponsors, the busiest languages/topics, and links to sponsor the work.')]
class GetSiteStatsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $s = SiteStats::all();

        $lines = [
            'GitHub repos: '.$s['repos'],
            'Catalogue pages (repos + articles + curated links): '.$s['catalogue'],
            'GitHub followers: '.$s['followers'],
            'Active sponsors: '.$s['public_sponsors'],
            'Total stars: '.$s['stars'],
            'Total downloads: '.$s['downloads'].' (Packagist: '.$s['downloads_packagist'].', npm: '.$s['downloads_npm'].', JetBrains: '.$s['downloads_jetbrains'].', Docker: '.$s['downloads_docker'].')',
            '',
            '## Packages by category',
            'Filament plugins: '.$s['filament'],
            'Laravel packages: '.$s['laravel'],
            'Livewire packages: '.$s['livewire'],
            'CakePHP packages: '.$s['cakephp'],
            'Laravel Zero CLIs: '.$s['laravel_zero'],
            'Starter kits: '.$s['starter'],
            'Actively maintained: '.$s['maintained'],
            'Daily-driver tools: '.$s['daily_drivers'],
        ];

        if ($s['languages'] !== []) {
            $lines[] = '';
            $lines[] = '## Top languages';
            foreach (array_slice($s['languages'], 0, 5) as $row) {
                $lines[] = '- '.$row['language'].': '.$row['total'];
            }
        }

        if ($s['topics'] !== []) {
            $lines[] = '';
            $lines[] = '## Top topics';
            foreach (array_slice($s['topics'], 0, 5) as $row) {
                $lines[] = '- '.$row['topic'].': '.$row['total'];
            }
        }

        $lines[] = '';
        $lines[] = '## Support this work';
        $lines[] = 'GitHub Sponsors: '.config('site.social.sponsors');
        $lines[] = 'Buy Me a Coffee: '.config('site.social.buymeacoffee');
        $lines[] = 'Patreon: '.config('site.social.patreon');

        return Response::text(implode("\n", $lines));
    }
}
