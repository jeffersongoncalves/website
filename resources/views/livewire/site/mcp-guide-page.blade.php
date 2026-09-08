<div>
    <section class="section section-first">
        <div class="wrap">
            <x-site.eyebrow num="01" :label="__('site.nav.mcp_guide')"/>
            <h1>@lang('site.mcp_guide.title')</h1>
            <p class="lede mt-6 max-w-[60ch]">@lang('site.mcp_guide.sub')</p>
        </div>
    </section>

    <div class="divider"></div>

    <section class="section">
        <div class="wrap">
            <article class="markdown-body" x-data="markdownCopy" x-init="enhance()">
                {!! $bodyHtml !!}
            </article>
        </div>
    </section>
</div>
