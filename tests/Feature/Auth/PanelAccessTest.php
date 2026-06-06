<?php

use App\Models\Admin;
use App\Models\User;

it('redirects guests from the admin panel to login', function () {
    $this->get('/admin')->assertRedirect();
});

it('lets a verified admin reach the admin panel', function () {
    $this->actingAs(Admin::factory()->create(['status' => true]), 'admin');

    $this->get('/admin')->assertOk();
});

it('keeps a web-guard user out of the admin panel', function () {
    // Authenticated on the `web` guard, not `admin` — the admin panel treats
    // them as a guest and bounces them to its own login.
    $this->actingAs(User::factory()->create(['status' => true]), 'web');

    $this->get('/admin')->assertRedirect();
});
