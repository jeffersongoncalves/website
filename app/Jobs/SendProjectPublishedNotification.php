<?php

namespace App\Jobs;

use App\Models\Project;
use App\Support\PushBroadcaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendProjectPublishedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public Project $project)
    {
        // Runs on the default queue, not the rate-limited `github` one —
        // pushing to FCM has nothing to do with the GitHub API budget.
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        // Guard against a double-publish (two saves landing close together)
        // firing two broadcasts for the same project.
        return [(new WithoutOverlapping("project-push:{$this->project->getKey()}"))->dontRelease()];
    }

    public function handle(): void
    {
        $title = __('admin.push.project_published.title');
        $body = (string) ($this->project->title ?: $this->project->name);
        $url = '/projects/'.$this->project->slug;

        try {
            $result = PushBroadcaster::send($title, $body, $url, 'project-'.$this->project->getKey());

            if (! $result['configured']) {
                Log::info('SendProjectPublishedNotification skipped — VAPID keys not configured', [
                    'project' => $this->project->slug,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('SendProjectPublishedNotification failed', [
                'project' => $this->project->slug,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
