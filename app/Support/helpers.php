<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use Illuminate\Support\Arr;

if (! function_exists('App\Support\enum_equals')) {
    /**
     * Compare a value (raw or BackedEnum) against a BackedEnum or list of them.
     *
     * @param  BackedEnum|array<BackedEnum>  $enum
     */
    function enum_equals(BackedEnum|string|int|null $value, BackedEnum|array $enum): bool
    {
        $raw = $value instanceof BackedEnum ? $value->value : $value;

        return $raw !== null && in_array($raw, array_column(Arr::wrap($enum), 'value'), true);
    }
}
