<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;

if (! function_exists('App\Support\enum_equals')) {
    /**
     * Compare a value (raw or BackedEnum) against a BackedEnum or list of them.
     *
     * @param  BackedEnum|array<BackedEnum>  $enum
     */
    function enum_equals(BackedEnum|string|int|null $value, BackedEnum|array $enum): bool
    {
        if (is_array($enum)) {
            return array_reduce($enum, fn (bool $carry, BackedEnum $case) => $carry || enum_equals($value, $case), false);
        }

        if (! $value instanceof BackedEnum) {
            // A null/absent value (e.g. an unset Filament form field) never
            // matches an enum case — and tryFrom() rejects null under strict types.
            return $value !== null && $enum::tryFrom($value) === $enum;
        }

        return $enum === $value;
    }
}
