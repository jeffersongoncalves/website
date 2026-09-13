<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Facades\Gate;

it('denies viewHorizon when no admin is authenticated', function () {
    expect(Gate::allows('viewHorizon'))->toBeFalse();
});

it('allows viewHorizon once authenticated on the admin guard', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    expect(Gate::allows('viewHorizon'))->toBeTrue();
});
