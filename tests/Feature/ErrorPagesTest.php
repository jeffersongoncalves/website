<?php

it('renders a themed 404 page', function () {
    $response = $this->get('/this-route-does-not-exist');

    $response->assertNotFound();
    $response->assertSee('error-page', false);
    $response->assertSee('error-code', false);
    $response->assertSee('404', false);
    $response->assertSee(__('site.errors.e404_title'), false);
    $response->assertSee(__('site.errors.home'), false);
});

it('renders the themed error layout for every error view', function () {
    foreach (['403', '404', '419', '429', '500', '503'] as $code) {
        $html = view("errors.{$code}")->render();

        expect($html)->toContain('error-page')
            ->and($html)->toContain($code)
            ->and($html)->toContain(__("site.errors.e{$code}_title"));
    }
});
