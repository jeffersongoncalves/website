<?php

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Jobs\SendProjectPublishedNotification;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('dispatches a push job when a project is created already published', function () {
    Project::query()->create([
        'name' => 'Published On Create',
        'slug' => 'published-on-create',
        'category' => ProjectCategory::Docker,
        'status' => ProjectStatus::Published,
    ]);

    Queue::assertPushed(SendProjectPublishedNotification::class, 1);
});

it('does not dispatch a push job for a draft project', function () {
    Project::query()->create([
        'name' => 'Still A Draft',
        'slug' => 'still-a-draft',
        'category' => ProjectCategory::Docker,
        'status' => ProjectStatus::Draft,
    ]);

    Queue::assertNotPushed(SendProjectPublishedNotification::class);
});

it('dispatches a push job when a draft transitions to published', function () {
    $project = Project::query()->create([
        'name' => 'Draft First',
        'slug' => 'draft-first',
        'category' => ProjectCategory::Docker,
        'status' => ProjectStatus::Draft,
    ]);

    $project->update(['status' => ProjectStatus::Published]);

    Queue::assertPushed(SendProjectPublishedNotification::class, 1);
});

it('does not re-dispatch when an already-published project is edited', function () {
    $project = Project::query()->create([
        'name' => 'Already Live',
        'slug' => 'already-live',
        'category' => ProjectCategory::Docker,
        'status' => ProjectStatus::Published,
    ]);

    // One dispatch from the create above; editing a non-status field
    // must not fire a second push.
    $project->update(['name' => 'Already Live (renamed)']);

    Queue::assertPushed(SendProjectPublishedNotification::class, 1);
});
