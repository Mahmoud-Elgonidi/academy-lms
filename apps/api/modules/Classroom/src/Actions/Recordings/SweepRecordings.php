<?php

namespace Modules\Classroom\Actions\Recordings;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Contracts\RecordingHolds;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Recordings\RecordingStore;
use Throwable;

/**
 * Sweeper (every five minutes): stops recordings whose lesson is over or
 * cancelled; settles ones the recorder went quiet about (a lost webhook: the
 * file is there, or it failed); and removes the files whose days are up,
 * unless held by the office or by a complaint still open about the lesson.
 */
final class SweepRecordings
{
    /** How long a stopped recording may wait for the recorder's word. */
    public const QUIET_MIN = 60;

    public function __construct(
        private readonly StopRecording $stop,
        private readonly RecordingStore $store,
        private readonly RecordingHolds $holds,
    ) {}

    /** @return array{stopped: int, settled: int, removed: int} */
    public function handle(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $counts = ['stopped' => 0, 'settled' => 0, 'removed' => 0];

        // Lessons over (and their closing minutes) or cancelled: their recording stops.
        $over = Recording::query()->whereIn('status', ['starting', 'recording'])
            ->whereIn('session_id', DB::table('sessions')->where(fn ($q) => $q->where('ends_at', '<', $now->subMinutes(config('classroom.closes_after_min')))
                ->orWhereNotIn('status', ['scheduled', 'in_progress']))->select('id'))
            ->get();
        foreach ($over as $recording) {
            try {
                $this->stop->handle($recording, null);
                $counts['stopped']++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Never got going, or the recorder went quiet after it was stopped (a lost webhook): what it left decides.
        $stale = Recording::query()->where(fn ($q) => $q->where('status', 'starting')->whereNull('egress_id')->where('created_at', '<', $now->subMinutes(10)))
            ->orWhere(fn ($q) => $q->where('status', 'ending')->where('updated_at', '<', $now->subMinutes(self::QUIET_MIN)))
            ->get();
        foreach ($stale as $recording) {
            if ($recording->status === 'ending' && $this->store->exists($recording)) {
                $started = $recording->started_at ?? $recording->created_at;
                $ended = $recording->updated_at->toImmutable();
                $recording->update(['status' => 'ready', 'size' => $this->store->size($recording), 'started_at' => $started, 'ended_at' => $ended,
                    'duration_sec' => max(0, (int) $started->diffInSeconds($ended)), 'expires_at' => $ended->addDays(ClassroomSettings::current()->recording_days)]);
            } else {
                $recording->update(['status' => 'failed', 'error' => 'no word from the recorder']);
            }
            $counts['settled']++;
        }

        // Days up, nothing holding it: the file goes, the row stays.
        $due = Recording::query()->where('status', 'ready')->whereNull('held_at')->where('expires_at', '<=', $now)->get();
        $held = $this->holds->heldSessions($due->pluck('session_id')->unique()->values()->all());
        foreach ($due as $recording) {
            if (isset($held[$recording->session_id])) {
                continue;
            }
            $recording->update(['status' => 'deleted', 'deleted_at' => $now, 'delete_reason' => 'expired']);
            $this->store->delete($recording);
            $counts['removed']++;
        }

        return $counts;
    }
}
