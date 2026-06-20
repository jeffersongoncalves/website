<?php

declare(strict_types=1);

// Architecture guardrails. Keep these conservative — they run on every push
// and a false positive blocks the whole suite.

arch('no debug statements leak into app code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'var_export', 'die'])
    ->not->toBeUsed();

arch('app code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('enums are real enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('jobs are queueable')
    ->expect('App\Jobs')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('observers live under the Observers namespace')
    ->expect('App\Observers')
    ->toHaveSuffix('Observer');

arch('support helpers are final or abstract utilities')
    ->expect('App\Http\Middleware')
    ->toHaveSuffix('')
    ->ignoring('App\Http\Middleware');

arch('controllers do not sit in the model layer')
    ->expect('App\Models')
    ->not->toUse(['App\Http\Controllers', 'App\Filament']);
