<?php

declare(strict_types=1);

it('renders the MCP guide page with the endpoint and setup snippets', function () {
    $this->get('/developers/mcp')
        ->assertOk()
        ->assertSee(url('/mcp'), false)
        ->assertSee('claude mcp add', false)
        ->assertSee('mcpServers', false);
});

it('lists the MCP guide in llms.txt', function () {
    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee(route('developers.mcp'), false);
});
