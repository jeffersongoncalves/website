<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Coarse grouping over the code-catalogue {@see ProjectCategory} cases, used to
 * collapse the ~17 catalogue categories into a handful of <optgroup>s on the
 * public /projects category filter. External-link categories (Website,
 * YoutubeChannel, …) and Article have no family — they live on /links and
 * /articles, not the catalogue.
 */
enum ProjectFamily: string implements HasLabel
{
    case PhpLaravel = 'php_laravel';
    case JsCss = 'js_css';
    case AppsTools = 'apps_tools';

    public function getLabel(): string
    {
        return match ($this) {
            self::PhpLaravel => __('site.projects.family_php_laravel'),
            self::JsCss => __('site.projects.family_js_css'),
            self::AppsTools => __('site.projects.family_apps_tools'),
        };
    }
}
