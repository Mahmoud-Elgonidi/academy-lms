<?php

namespace Modules\SharedKernel\Realtime\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Health\Heartbeats;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Realtime\RelayPulse;
use Modules\SharedKernel\Realtime\SignalFolder;
use PDO;
use Throwable;

/**
 * Passes changes from Postgres to the open screens. Table triggers raise
 * pg_notify('lms_live', '{topic}:{id}') when a change commits; this process
 * waits on them, folds bursts together (SignalFolder) and broadcasts, through
 * Reverb, the event the topic's module registered in LiveSignals. Idle, it
 * only sleeps on the socket: no queries, no polling.
 *
 * Runs next to `reverb:start` for as long as the app runs (docker compose
 * starts both). It stops by itself after `queue:restart`, like the queue
 * worker, so a deploy brings it back on the new code.
 */
final class LiveRelayCommand extends Command
{
    private const PULSE_MS = 60_000;

    protected $signature = 'live:relay {--for= : stop after this many seconds}';

    protected $description = 'Send changes from the database to the open screens (needs reverb:start)';

    /** The name it had while only the live board used it (older start scripts call it). */
    protected $aliases = ['scheduling:live'];

    private float $stopAt = INF;

    private float $nextPulse = 0;

    private mixed $restartSignal = null;

    private bool $restart = false;

    public function handle(LiveSignals $signals): int
    {
        if ($this->option('for') !== null) {
            $this->stopAt = $this->now() + (float) $this->option('for') * 1000;
        }
        $this->restartSignal = Cache::get('illuminate:queue:restart');

        while (! $this->shouldStop()) {
            try {
                $this->listen($signals);
            } catch (Throwable $e) {
                report($e);
                $this->components->warn('Live relay: '.$e->getMessage().'; reconnecting.');
                usleep(2_000_000);
                DB::purge();
            }
        }

        return self::SUCCESS;
    }

    private function listen(LiveSignals $signals): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->exec('listen '.LiveSignals::PG_CHANNEL);
        $folder = new SignalFolder;
        $this->pulse(resync: true);   // open screens catch up on what they may have missed while nobody listened
        $this->components->info('Live relay is listening.');

        while (! $this->shouldStop()) {
            $now = $this->now();
            $wait = min($folder->waitMs($now) ?? PHP_INT_MAX, (int) max(0, $this->nextPulse - $now), (int) max(0, $this->stopAt - $now));
            $raised = $this->next($pdo, $wait);
            if ($raised !== null) {
                $folder->changed($raised, $this->now());

                continue;
            }

            foreach ($folder->take($this->now()) as $key) {
                if ($event = $signals->event($key)) {
                    $this->send($event);
                }
            }
            if ($this->now() >= $this->nextPulse) {
                $pdo->query('select 1');   // the connection is still there, or this throws and we reconnect
                $this->restart = Cache::get('illuminate:queue:restart') !== $this->restartSignal;
                $this->pulse();
            }
        }
    }

    /** The next "{topic}:{id}" raised by the database, or null after $waitMs with none. */
    private function next(PDO $pdo, int $waitMs): ?string
    {
        $note = method_exists($pdo, 'getNotify')
            ? $pdo->getNotify(PDO::FETCH_ASSOC, $waitMs)
            : $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, $waitMs);

        return is_array($note) && ($note['payload'] ?? '') !== '' ? (string) $note['payload'] : null;
    }

    private function pulse(bool $resync = false): void
    {
        $this->send(new RelayPulse($resync));
        Heartbeats::beat('relay');
        $this->nextPulse = $this->now() + self::PULSE_MS;
    }

    /** A screen that misses a signal catches up later; a failed send never stops the relay. */
    private function send(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function shouldStop(): bool
    {
        return $this->restart || $this->now() >= $this->stopAt;
    }

    private function now(): float
    {
        return microtime(true) * 1000;
    }
}
