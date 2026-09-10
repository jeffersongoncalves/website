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
        <div class="wrap" x-data="{ tab: 'claude-code' }">
            <h2 class="text-xl font-semibold text-[var(--text-strong)]">{{ __('site.mcp_guide.setup_title') }}</h2>
            <p class="mono-meta mt-2">{{ __('site.mcp_guide.setup_note') }}</p>

            <div class="flex flex-wrap gap-2 mt-4">
                @foreach (['claude-code' => 'Claude Code', 'claude-desktop' => 'Claude Desktop', 'cursor' => 'Cursor', 'vscode' => 'VS Code', 'codex' => 'Codex', 'gemini-cli' => 'Gemini CLI'] as $key => $label)
                    <button type="button" class="chip" :class="{ 'chip-active': tab === '{{ $key }}' }" @click="tab = '{{ $key }}'">{{ $label }}</button>
                @endforeach
            </div>

            <div class="markdown-body mt-6" x-data="markdownCopy" x-init="enhance()">
                <div x-show="tab === 'claude-code'">
                    <pre><code class="language-bash">claude mcp add --transport http portfolio {{ $endpoint }}</code></pre>
                </div>
                <div x-show="tab === 'claude-desktop'" x-cloak>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>{{ __('site.mcp_guide.desktop_step_1') }}</li>
                        <li>{{ __('site.mcp_guide.desktop_step_2') }}</li>
                        <li>{{ __('site.mcp_guide.desktop_step_3') }}</li>
                    </ol>
                    <pre class="mt-3"><code class="language-text">{{ $endpoint }}</code></pre>
                </div>
                <div x-show="tab === 'cursor'" x-cloak>
                    <p class="mono-meta mb-2">.cursor/mcp.json</p>
                    <pre><code class="language-json">{
  "mcpServers": {
    "portfolio": {
      "url": "{{ $endpoint }}"
    }
  }
}</code></pre>
                </div>
                <div x-show="tab === 'vscode'" x-cloak>
                    <p class="mono-meta mb-2">.vscode/mcp.json</p>
                    <pre><code class="language-json">{
  "servers": {
    "portfolio": {
      "type": "http",
      "url": "{{ $endpoint }}"
    }
  }
}</code></pre>
                </div>
                <div x-show="tab === 'codex'" x-cloak>
                    <p class="mono-meta mb-2">~/.codex/config.toml</p>
                    <pre><code class="language-toml">[mcp_servers.portfolio]
url = "{{ $endpoint }}"</code></pre>
                </div>
                <div x-show="tab === 'gemini-cli'" x-cloak>
                    <p class="mono-meta mb-2">~/.gemini/settings.json</p>
                    <pre><code class="language-json">{
  "mcpServers": {
    "portfolio": {
      "httpUrl": "{{ $endpoint }}"
    }
  }
}</code></pre>
                </div>
            </div>
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
