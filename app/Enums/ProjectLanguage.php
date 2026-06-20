<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Primary repository language as reported by GitHub (Linguist). Backing values
 * match GitHub's exact language names so ProjectLanguage::tryFrom() maps a raw
 * `/repos` `language` straight onto a case; anything not listed resolves to
 * null and the project simply carries no language facet.
 */
enum ProjectLanguage: string implements HasColor, HasLabel
{
    case Php = 'PHP';
    case Blade = 'Blade';
    case JavaScript = 'JavaScript';
    case TypeScript = 'TypeScript';
    case Vue = 'Vue';
    case Svelte = 'Svelte';
    case Astro = 'Astro';
    case Css = 'CSS';
    case Scss = 'SCSS';
    case Less = 'Less';
    case Html = 'HTML';
    case Twig = 'Twig';
    case Handlebars = 'Handlebars';
    case Mdx = 'MDX';
    case Python = 'Python';
    case Ruby = 'Ruby';
    case Go = 'Go';
    case Rust = 'Rust';
    case Java = 'Java';
    case Kotlin = 'Kotlin';
    case Swift = 'Swift';
    case ObjectiveC = 'Objective-C';
    case C = 'C';
    case Cpp = 'C++';
    case CSharp = 'C#';
    case Shell = 'Shell';
    case PowerShell = 'PowerShell';
    case Dockerfile = 'Dockerfile';
    case Dart = 'Dart';
    case Elixir = 'Elixir';
    case Clojure = 'Clojure';
    case Scala = 'Scala';
    case Lua = 'Lua';
    case Perl = 'Perl';
    case Haskell = 'Haskell';
    case Zig = 'Zig';
    case JupyterNotebook = 'Jupyter Notebook';
    case Makefile = 'Makefile';
    case TeX = 'TeX';
    case CoffeeScript = 'CoffeeScript';

    public function getLabel(): string
    {
        // Language names are proper nouns — display the backing value verbatim.
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Php, self::TypeScript, self::Css, self::Scss, self::Less,
            self::ObjectiveC, self::Cpp, self::Dockerfile, self::Dart,
            self::Lua, self::Perl, self::PowerShell, self::Go => 'info',
            self::JavaScript, self::Html, self::Rust, self::Kotlin,
            self::Swift, self::Zig, self::Handlebars, self::JupyterNotebook => 'warning',
            self::Blade, self::Svelte, self::Ruby, self::Java, self::Scala => 'danger',
            self::Vue, self::Twig, self::Clojure, self::CSharp => 'success',
            self::Elixir, self::Haskell => 'primary',
            self::Astro, self::Mdx, self::C, self::Shell,
            self::Makefile, self::TeX, self::CoffeeScript, self::Python => 'gray',
        };
    }
}
