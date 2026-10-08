<?php

namespace Modules\Classroom\Actions\Recordings;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\RecordingConsent;
use Modules\Classroom\Recordings\RecordingRules;
use Modules\SharedKernel\Audit\AuditTrail;
use Throwable;

/**
 * Someone takes their consent back (a teacher or an adult student for
 * themself, a guardian for their child), or the office does at their request,
 * saying so in the note. The consent is marked, never removed; a recording
 * running in one of their lessons stops now, and nothing more is recorded.
 * What was already recorded keeps its days.
 */
final class WithdrawRecordingConsent
{
    public function __construct(
        private readonly RecordingRules $rules,
        private readonly StopRecording $stop,
        private readonly AuditTrail $audit,
    ) {}

    /** @param  bool  $asStaff  the office, which says why; else the person (or the child's guardian) themself */
    public function handle(string $personId, string $byUserId, bool $asStaff = false, ?string $note = null): RecordingConsent
    {
        $note = trim((string) $note) ?: null;
        if (! $asStaff && $this->rules->wayFor($byUserId, $personId) === null) {
            throw new AuthorizationException;
        }
        if ($asStaff && ($note === null || mb_strlen($note) < RecordRecordingConsent::NOTE_MIN)) {
            throw ValidationException::withMessages(['note' => __('classroom::recordings.errors.note', ['min' => RecordRecordingConsent::NOTE_MIN])]);
        }

        $consent = DB::transaction(function () use ($personId, $byUserId, $note) {
            $consent = RecordingConsent::query()->live()->where('person_id', $personId)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['person' => __('classroom::recordings.errors.no_consent')]);
            $consent->forceFill(['withdrawn_at' => now(), 'withdrawn_by' => $byUserId, 'withdraw_note' => $note !== null ? mb_substr($note, 0, 300) : null])->save();
            $names = DB::table('users')->whereIn('id', [$personId, $byUserId])->pluck('name', 'id');
            $this->audit->record('classroom.recording_consent_withdrawn', 'user', $personId, [
                'person' => $names[$personId] ?? '', 'by' => $names[$byUserId] ?? '',
            ]);

            return $consent;
        });

        foreach ($this->runningWith($personId) as $recording) {
            try {
                $this->stop->handle($recording, $byUserId);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $consent;
    }

    /** @return list<Recording> recordings running in a lesson this person teaches or attends */
    private function runningWith(string $personId): array
    {
        return Recording::query()->running()
            ->whereIn('session_id', DB::table('sessions')->where('teacher_id', $personId)->select('id'))
            ->orWhere(fn ($q) => $q->running()->whereIn('session_id', DB::table('session_participants')->where('user_id', $personId)->select('session_id')))
            ->get()->all();
    }
}
