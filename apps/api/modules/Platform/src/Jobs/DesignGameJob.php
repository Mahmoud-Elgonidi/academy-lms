<?php

namespace Modules\Platform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Platform\Actions\Games\DesignGame;
use Throwable;

/** One round of the designer on a draft, off the request: it takes minutes, so it has its own queue and worker (entrypoint.sh's worker part). */
final class DesignGameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 1000;

    public function __construct(public readonly string $draftId)
    {
        // Its own worker (the designer queue), unless the app runs its queue in-process.
        if (config('queue.default') === 'sync') {
            $this->onConnection('sync');
        } else {
            $this->onConnection('designer')->onQueue('designer');
        }
    }

    public function handle(DesignGame $design): void
    {
        $design->handle($this->draftId);
    }

    public function failed(?Throwable $e): void
    {
        app(DesignGame::class)->failed($this->draftId, 'unavailable', $e ? class_basename($e) : '');
    }
}
