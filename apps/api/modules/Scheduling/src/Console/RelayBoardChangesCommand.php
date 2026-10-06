<?php

namespace Modules\Scheduling\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Live\BoardChanged;
use Modules\Scheduling\Live\BoardSignals;
use Modules\Scheduling\Live\RelayPulse;
use PDO;
use Throwable;

/**
 * Passes "this academy's live board changed" from Postgres to the browsers.
 * The database raises it on every committed change to a lesson (see the
 * migration notify_live_board_changes); this process waits on it, folds
 * bursts together (BoardSignals) and broadcasts one BoardChanged per academy
 * through Reverb. Idle, it only sleeps on the socket: no queries, no polling.
 *
 * Runs next to `reverb:start` for as long as the app runs (docker compose
 * starts both). It stops by itself after `queue:restart`, like the queue
 * worker, so a deploy brings it back on the new code.
 */
final class RelayBoardChangesCommand extends Command
{
    public const CHANNEL = 'lms_board';

    private const PULSE_MS = 60_000;

    protected $signature = 'scheduling:live {--for= : stop after this many seconds}';

    protected $description = 'Send live board changes from the database to the open boards (needs reverb:start)';

    private float $stopAt = INF;

    private float $nextPulse = 0;

    private mixed $restartSignal = null;

    private bool $restart = false;

    public function handle(): int
    {
        if ($this->option('for') !== null) {
            $this->stopAt = $this->now() + (float) $this->option('for') * 1000;
        }
        $this->restartSignal = Cache::get('illuminate:queue:restart');
        $resync = true;

        while (! $this->shouldStop()) {
            try {
                $this->listen($resync);
            } catch (Throwable $e) {
                report($e);
                $this->components->warn('Live board relay: '.$e->getMessage().'; reconnecting.');
                usleep(2_000_000);
                DB::purge();
            }
            $resync = true;   // after a lost connection, boards catch up on what they missed
        }

        return self::SUCCESS;
    }

    private function listen(bool $resync): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->exec('listen '.self::CHANNEL);
        $signals = new BoardSignals;
        $this->pulse($resync);
        $this->components->info('Live board relay is listening.');

        while (! $this->shouldStop()) {
            $now = $this->now();
            $wait = min($signals->waitMs($now) ?? PHP_INT_MAX, (int) max(0, $this->nextPulse - $now), (int) max(0, $this->stopAt - $now));
            $note = $this->next($pdo, $wait);
            if ($note !== null) {
                $signals->changed($note, $this->now());

                continue;
            }

            foreach ($signals->take($this->now()) as $academyId) {
                $this->send(new BoardChanged($academyId));
            }
            if ($this->now() >= $this->nextPulse) {
                $pdo->query('select 1');   // the connection is still there, or this throws and we reconnect
                $this->restart = Cache::get('illuminate:queue:restart') !== $this->restartSignal;
                $this->pulse();
            }
        }
    }

    /** The next academy id raised by the database, or null after $waitMs with none. */
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
        $this->nextPulse = $this->now() + self::PULSE_MS;
    }

    /** A board that misses a signal catches up later; a failed send never stops the relay. */
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
