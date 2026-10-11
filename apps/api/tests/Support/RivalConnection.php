<?php

namespace Tests\Support;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The other side of a race, against the real database: a second connection
 * as the app, in the test's academy ($this->academy), that commits in the gap
 * between this side's read and its write. Deterministic, no sleeps: the gap
 * is found by the statement this side is about to send.
 */
trait RivalConnection
{
    protected function rival(): Connection
    {
        if (! array_key_exists('pgsql_rival', config('database.connections'))) {
            config(['database.connections.pgsql_rival' => config('database.connections.'.config('database.default'))]);
        }
        $rival = DB::connection('pgsql_rival');
        $rival->select("select set_config('app.tenant_id', ?, false)", [$this->academy->id]);

        return $rival;
    }

    /** Just before this side first sends a statement starting with $sql, the other side does $then($bindings). */
    protected function rivalCommitsBefore(string $sql, Closure $then): void
    {
        $fired = false;
        DB::connection()->beforeExecuting(function (string $query, array $bindings) use ($sql, $then, &$fired) {
            if (! $fired && str_starts_with($query, $sql)) {
                $fired = true;
                $then($bindings);
            }
        });
    }

    /** Right after this side's $nth read with a statement starting with $sql, before its next statement, the other side does $then. */
    protected function rivalCommitsAfter(string $sql, Closure $then, int $nth = 1): void
    {
        $seen = 0;
        $state = 'waiting';
        DB::connection()->beforeExecuting(function (string $query) use ($sql, $then, $nth, &$seen, &$state) {
            if ($state === 'read') {
                $state = 'done';
                $then();
            } elseif ($state === 'waiting' && str_starts_with($query, $sql) && ++$seen === $nth) {
                $state = 'read';
            }
        });
    }

    /**
     * The other side books a lesson for a teacher and a student, committed.
     *
     * @param  array{enrollment_id?: string, slot_id?: string, occurrence_key?: string}  $extra
     */
    protected function rivalBooks(string $teacherId, string $studentId, string $subjectId, CarbonImmutable $start, int $minutes = 30, array $extra = []): string
    {
        $id = (string) Str::uuid();
        [$from, $to] = [$start->utc()->toIso8601String(), $start->addMinutes($minutes)->utc()->toIso8601String()];
        $rival = $this->rival();
        $rival->transaction(function () use ($rival, $id, $teacherId, $studentId, $subjectId, $from, $to, $extra) {
            $rival->insert(
                'insert into sessions (id, type, teacher_id, subject_id, enrollment_id, slot_id, occurrence_key, starts_at, ends_at, funding_status, anchor_tz, created_at, updated_at)
                 values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now(), now())',
                [$id, 'regular', $teacherId, $subjectId, $extra['enrollment_id'] ?? null, $extra['slot_id'] ?? null, $extra['occurrence_key'] ?? null, $from, $to, 'unfunded', 'UTC'],
            );
            foreach (['teacher' => $teacherId, 'student' => $studentId] as $role => $userId) {
                $rival->insert('insert into session_participants (session_id, user_id, role, block_period) values (?, ?, ?, tstzrange(?, ?))', [$id, $userId, $role, $from, $to]);
            }
        });

        return $id;
    }

    /** The other side cancels a lesson, as CancelSession leaves it (committed). */
    protected function rivalCancels(string $sessionId): void
    {
        $rival = $this->rival();
        $rival->transaction(function () use ($rival, $sessionId) {
            $rival->update("update sessions set status = 'cancelled', version = version + 1, updated_at = now() where id = ?", [$sessionId]);
            $rival->update('update session_participants set is_active = false where session_id = ?', [$sessionId]);
        });
    }
}
