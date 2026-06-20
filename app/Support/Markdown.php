<?php

declare(strict_types=1);

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use Tempest\Highlight\CommonMark\CodeBlockRenderer;
use Tempest\Highlight\Highlighter;
use Tempest\Highlight\Themes\CssTheme;

/**
 * Shared CommonMark renderer for the two markdown surfaces on the site —
 * GitHub READMEs and imported article bodies. Both render with raw HTML
 * enabled (the caller is expected to run the output through
 * {@see HtmlSanitizer} before display) and get server-side syntax highlighting
 * on fenced code blocks via tempest/highlight's CssTheme: it emits
 * class-based `<span class="hl-…">` tokens (styled in site.css) that survive
 * sanitisation, rather than inline styles which the sanitiser would strip.
 *
 * Only the block-level FencedCode renderer is overridden — inline `code`
 * keeps its plain rendering.
 */
class Markdown
{
    public static function render(string $markdown, bool $headingPermalinks = false): string
    {
        $config = [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ];

        if ($headingPermalinks) {
            $config['heading_permalink'] = [
                'symbol' => '#',
                'html_class' => 'md-anchor',
            ];
        }

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);

        if ($headingPermalinks) {
            $environment->addExtension(new HeadingPermalinkExtension);
        }

        // Priority 10 outranks GFM's default fenced-code renderer.
        $environment->addRenderer(
            FencedCode::class,
            new CodeBlockRenderer(new Highlighter(new CssTheme)),
            10,
        );

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }
}
