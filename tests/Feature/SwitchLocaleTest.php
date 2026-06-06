<?php

use App\Http\Middleware\SetLocale;

it('switches the locale cookie and returns to a same-host referer', function () {
    $this->withHeaders(['referer' => url('/projects')])
        ->get('/locale/en')
        ->assertRedirect(url('/projects'))
        ->assertCookie(SetLocale::COOKIE_NAME, 'en');
});

it('ignores a cross-origin referer and falls back home (open-redirect guard)', function () {
    $this->withHeaders(['referer' => 'https://evil.example.com/phish'])
        ->get('/locale/en')
        ->assertRedirect('/');
});

it('falls back home when no referer is present', function () {
    $this->get('/locale/pt')
        ->assertRedirect('/')
        ->assertCookie(SetLocale::COOKIE_NAME, 'pt');
});

it('rejects an unsupported locale at the route level', function () {
    $this->get('/locale/de')->assertNotFound();
});
