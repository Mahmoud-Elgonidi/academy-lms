<?php

namespace Modules\Classroom\Actions\Recordings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Models\RecordingConsent;
use Modules\Classroom\Recordings\RecordingRules;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The office records a consent it holds on paper: a guardian's signed form,
 * an adult student's, or the recording clause in a teacher's contract. The
 * note says what it has. A consent already standing is kept as it is.
 */
final class RecordRecordingConsent
{
    public const NOTE_MIN = 10;

    public function __construct(
        private readonly RecordingRules $rules,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $personId, string $staffId, ?string $note): RecordingConsent
    {
        $note = trim((string) $note);
        Validator::make(['note' => $note], ['note' => ['required', 'string', 'min:'.self::NOTE_MIN, 'max:300']], [], ['note' => __('classroom::recordings.attributes.note')])->validate();
        $role = $this->rules->roleOf($personId) ?? throw ValidationException::withMessages(['person' => __('classroom::recordings.errors.not_recorded')]);
        if ($live = RecordingConsent::liveFor($personId)) {
            return $live;
        }

        return DB::transaction(function () use ($personId, $staffId, $note, $role) {
            $consent = RecordingConsent::query()->create([
                'person_id' => $personId,
                'role' => $role,
                'via' => 'staff',
                'recorded_by' => $staffId,
                'note' => $note,
                'version' => RecordingConsent::VERSION,
                'granted_at' => now(),
            ]);
            $this->audit->record('classroom.recording_consent_recorded', 'user', $personId, [
                'person' => (string) DB::table('users')->where('id', $personId)->value('name'), 'note' => $note,
            ]);

            return $consent;
        });
    }
}
