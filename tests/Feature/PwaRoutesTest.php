<?php

it('serves the service worker script at the site root', function () {
    $this->get('/sw.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertHeader('Service-Worker-Allowed', '/');
});

it('serves the static offline fallback page', function () {
    $this->get('/offline')->assertOk();
});
