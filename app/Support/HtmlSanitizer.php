<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitises rendered HTML that originated from untrusted sources — GitHub
 * READMEs of starred third-party repos and the markdown body of imported
 * articles. Both render with raw-HTML enabled (CommonMark `html_input: allow`
 * / Str::markdown), so a crafted source could ship `<script>` or an inline
 * event handler (`<img onerror=...>`); the site's CSP keeps `'unsafe-inline'`
 * for Alpine, so such handlers would otherwise execute.
 *
 * Drops scripts, styles and every event-handler attribute while keeping the
 * presentational subset a README/article needs (headings, lists, tables,
 * code blocks, images, links). Our own post-processing (target/rel, lazy
 * loading, table wrappers) runs AFTER this and re-adds those safe attributes.
 */
class HtmlSanitizer
{
    public static function clean(string $html): string
    {
        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): SymfonyHtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http', 'data'])
            // Heading permalinks, code-language hints and our table wrappers
            // lean on class names for styling — keep them.
            ->allowAttribute('class', '*')
            ->allowAttribute('id', '*')
            // Preserve author image sizing on screenshots. READMEs lay out image
            // galleries with `<img width="20%">` to flow several per row; without
            // these attributes every image falls back to max-width:100% and
            // stacks one per line. Scoped to img (value is a dimension, no CSS).
            ->allowAttribute('width', ['img'])
            ->allowAttribute('height', ['img']);

        return new SymfonyHtmlSanitizer($config);
    }
}
