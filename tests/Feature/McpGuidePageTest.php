<?php

declare(strict_types=1);

it('renders the MCP guide page with the endpoint and setup snippets', function () {
    $this->get('/pt_BR/developers/mcp')
        ->assertOk()
        ->assertSee(url('/mcp'), false)
        ->assertSee('claude mcp add', false)
        ->assertSee('mcpServers', false);
});

it('lists the MCP guide in llms.txt', function () {
    $this->artisan('llms:generate')->assertSuccessful();

    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee(route('developers.mcp'), false);
});

it('renders the Spanish MCP guide body', function () {
    // Locale now lives in the URL segment (see routes/web.php's
    // Route::prefix('{locale}') group + SetLocaleFromRoute), not the cookie.
    $this->get('/es/developers/mcp')
        ->assertOk()
        ->assertSee('Este sitio publica un servidor', false)
        ->assertSee('Probarlo localmente', false);
});

it('renders the English MCP guide body', function () {
    $this->get('/en/developers/mcp')
        ->assertOk()
        ->assertSee('Test it locally', false);
});

it('runs the sanitized body through HtmlSanitizer::clean without mangling a fenced code block', function () {
    // McpGuidePage::body() is 100% static, developer-authored markdown — no
    // attacker-controlled input ever reaches it, so this isn't an XSS test in
    // the adversarial sense (there's nothing to inject); the real
    // attacker-controlled sanitizer path in this app is ProjectShowPage's
    // README rendering, covered separately in ProjectShowPageTest. This is a
    // sanity check that the render()->HtmlSanitizer::clean() pipeline
    // (McpGuidePage.php:28) still runs end-to-end and doesn't corrupt the
    // one piece of markup-shaped content this static page has.
    $this->get('/pt_BR/developers/mcp')
        ->assertOk()
        ->assertSee('<code class="language-bash">', false)
        ->assertSee('mcp:inspector', false);
});
