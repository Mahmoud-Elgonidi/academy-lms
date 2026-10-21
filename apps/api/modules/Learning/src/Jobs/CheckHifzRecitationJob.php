<?php

namespace Modules\Learning\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Learning\Actions\Hifz\CheckHifzRecitation;
use Modules\Learning\Models\HifzRecitation;
use Throwable;

/** A page recited for the daily review, heard on the AI helpers' own queue (the academy travels with the job). */
final class CheckHifzRecitationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 220;

    public function __construct(public readonly string $recitationId)
    {
        $this->onConnection('assist');
        $this->onQueue('assist');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [20, 90];
    }

    public function handle(CheckHifzRecitation $check): void
    {
        $row = HifzRecitation::query()->find($this->recitationId);
        if ($row !== null) {
            $check->handle($row);
        }
    }

    /** Busy or down every time: the learner sees it could not be heard (and may grade it themselves). */
    public function failed(?Throwable $e): void
    {
        $row = HifzRecitation::query()->find($this->recitationId);
        if ($row?->status === 'pending') {
            CheckHifzRecitation::failed($row, 'unavailable');
        }
    }
}
