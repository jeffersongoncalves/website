<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Polling Interval
    |--------------------------------------------------------------------------
    |
    | Global default applied to Filament's own polling-capable widgets
    | (StatsOverviewWidget, ChartWidget). Set to null to disable polling by
    | default. Only applied to widgets that have NOT customized their own
    | `$pollingInterval` — see WidgetConfigurationPlugin::apply().
    |
    */
    'polling_interval' => env('FILAMENT_WIDGET_CONFIGURATION_POLLING_INTERVAL', '60s'),

    /*
    |--------------------------------------------------------------------------
    | Lazy Loading
    |--------------------------------------------------------------------------
    |
    | Global default applied to `Filament\Widgets\Widget::$isLazy`. Only
    | applied to widgets that have NOT customized their own lazy-loading
    | behavior.
    |
    */
    'lazy' => env('FILAMENT_WIDGET_CONFIGURATION_LAZY', true),

];
