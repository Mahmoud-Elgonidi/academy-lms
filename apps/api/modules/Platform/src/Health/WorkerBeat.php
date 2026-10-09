<?php

namespace Modules\Platform\Health;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\SharedKernel\Health\Heartbeats;

/** Sent by the scheduler each minute to the lowest queue: the worker's heartbeat, and how long it waited for one. */
final class WorkerBeat implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $sentAt) {}

    public function handle(): void
    {
        Heartbeats::beat('worker', ['lag' => max(0, now()->getTimestamp() - $this->sentAt)]);
    }
}
