<?php

namespace Modules\Platform\Ai;

use Closure;
use Illuminate\Support\Facades\Cache;
use Modules\SharedKernel\Ai\AiFailed;

/**
 * Few requests to the providers at once while someone waits on a screen
 * (AI_CONCURRENT): the web server has only a few threads, and a teacher's
 * draft must not hold them all after a busy hour of lessons. Each request
 * takes a free slot (a cache lock) or is told the helper is busy. The
 * workers run without slots: their number already bounds them.
 */
final class AiSlots
{
    /** A person is waiting on this request (a web request, or a test standing for one). */
    public function waiting(): bool
    {
        return ! app()->runningInConsole() || app()->runningUnitTests();
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function hold(Closure $work): mixed
    {
        if (! $this->waiting()) {
            return $work();
        }
        $slots = max(1, (int) config('platform.ai.concurrent', 3));
        $seconds = (int) config('platform.ai.timeout', 40) + 20;
        for ($i = 0; $i < $slots; $i++) {
            $lock = Cache::lock("ai:slot:{$i}", $seconds);
            if ($lock->get()) {
                try {
                    return $work();
                } finally {
                    $lock->release();
                }
            }
        }

        throw new AiFailed('busy');
    }
}
