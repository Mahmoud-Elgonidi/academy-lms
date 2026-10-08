<?php

namespace Modules\Classroom\Actions\Recordings;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Models\RecordingConsent;
use Modules\Classroom\Recordings\RecordingRules;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Someone agrees to lessons being recorded, to the wording they were shown
 * (RecordingConsent::VERSION): a teacher or an adult student for themself, a
 * guardian for their child. The address and browser are kept with it.
 * Agreeing again while a consent stands changes nothing.
 */
final class GiveRecordingConsent
{
    public function __construct(
        private readonly RecordingRules $rules,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $personId, string $byUserId, int $version = RecordingConsent::VERSION, ?string $ip = null, ?string $userAgent = null): RecordingConsent
    {
        $way = $this->rules->wayFor($byUserId, $personId) ?? throw new AuthorizationException;
        if ($version !== RecordingConsent::VERSION) {
            throw ValidationException::withMessages(['version' => __('classroom::recordings.errors.wording_changed')]);
        }
        if ($live = RecordingConsent::liveFor($personId)) {
            return $live;
        }

        try {
            return DB::transaction(function () use ($personId, $byUserId, $way, $ip, $userAgent) {
                $consent = RecordingConsent::query()->create([
                    'person_id' => $personId,
                    'role' => $this->rules->roleOf($personId),
                    'given_by' => $byUserId,
                    'via' => $way,
                    'version' => RecordingConsent::VERSION,
                    'ip' => $ip !== null ? Str::limit($ip, 45, '') : null,
                    'user_agent' => $userAgent !== null ? Str::limit($userAgent, 300, '') : null,
                    'granted_at' => now(),
                ]);
                $names = DB::table('users')->whereIn('id', [$personId, $byUserId])->pluck('name', 'id');
                $this->audit->record('classroom.recording_consent_given', 'user', $personId, [
                    'person' => $names[$personId] ?? '', 'by' => $names[$byUserId] ?? '', 'via' => $way,
                ]);

                return $consent;
            });
        } catch (UniqueConstraintViolationException) {
            // Two guardians, or two taps, at once: the first one stands.
            return RecordingConsent::liveFor($personId) ?? throw ValidationException::withMessages(['person' => __('classroom::recordings.errors.try_again')]);
        }
    }
}
