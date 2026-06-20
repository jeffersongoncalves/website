<?php

declare(strict_types=1);

namespace App\Support;

abstract class LocaleSupport
{
    /**
     * Reduce the application's current locale (`pt_BR`, `pt-BR`, `pt`) to its
     * short language code (`pt`). Safe when the locale has no region suffix.
     */
    public static function short(): string
    {
        $base = preg_split('/[_-]/', app()->getLocale())[0] ?? '';
        $base = strtolower($base);

        return $base !== '' ? $base : 'en';
    }
}
