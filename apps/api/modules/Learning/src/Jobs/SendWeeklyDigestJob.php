<?php

namespace Modules\Learning\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Learning\Actions\Digest\SendWeeklyDigest;
use Modules\Learning\Models\WeeklyDigest;
use Throwable;

/** A learner's weekly note, on the AI helpers' own queue (the academy travels with the job). */
final class SendWeeklyDigestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly string $digestId)
    {
        $this->onConnection('assist');
        $this->onQueue('assist');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(SendWeeklyDigest $send): void
    {
        $digest = WeeklyDigest::query()->find($this->digestId);
        if ($digest !== null) {
            $send->handle($digest);
        }
    }

    /** It could not go out this week: kept as such, never sent twice. */
    public function failed(?Throwable $e): void
    {
        WeeklyDigest::query()->whereKey($this->digestId)->where('status', 'pending')->update(['status' => 'skipped', 'reason' => 'failed']);
    }
}
