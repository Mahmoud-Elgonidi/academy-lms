<?php

namespace Modules\Quran\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Quran\Actions\Recitation\CheckRecitation;
use Modules\Quran\Models\SubmissionCheck;
use Throwable;

/** A recording's check, on the AI helpers' own queue (the academy travels with the job). */
final class CheckRecitationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 220;

    public function __construct(public readonly string $checkId)
    {
        $this->onConnection('assist');
        $this->onQueue('assist');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(CheckRecitation $check): void
    {
        $row = SubmissionCheck::query()->find($this->checkId);
        if ($row !== null) {
            $check->handle($row);
        }
    }

    /** Busy or down every time: the teacher sees it could not be checked. */
    public function failed(?Throwable $e): void
    {
        $row = SubmissionCheck::query()->find($this->checkId);
        if ($row?->status === 'pending') {
            CheckRecitation::failed($row, 'unavailable');
        }
    }
}
