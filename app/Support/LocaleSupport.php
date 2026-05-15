<?php

namespace App\Support;

abstract class LocaleSupport
{
    /**
     * Reduce a Laravel locale (`pt_BR`, `pt-BR`, `pt`) to its short language
     * code (`pt`). Safe when the locale has no region suffix.
     */
    public static function short(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $base = preg_split('/[_-]/', $locale)[0] ?? '';
        $base = strtolower($base);

        return $base !== '' ? $base : 'en';
    }
}
