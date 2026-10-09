<?php

namespace Modules\People\Actions\Checks;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Checks\Events\TeacherCheckDecided;
use Modules\People\Models\TeacherCheck;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The academy looked at a teacher's document: verified, valid until a day it
 * sets (none: it does not run out), or turned down with a reason the teacher
 * reads. A verified one found wrong later is turned down the same way. In
 * the activity log; the teacher hears either way.
 */
final class DecideTeacherCheck
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(string $checkId, bool $verified, ?CarbonImmutable $expiresOn, ?string $note, string $actorId): TeacherCheck
    {
        $note = trim((string) $note) ?: null;
        if (! $verified && mb_strlen((string) $note) < 5) {
            throw ValidationException::withMessages(['note' => __('people::checks.errors.reason')]);
        }
        if ($note !== null && mb_strlen($note) > 500) {
            throw ValidationException::withMessages(['note' => __('validation.max.string', ['attribute' => __('people::checks.fields.note'), 'max' => 500])]);
        }

        return DB::transaction(function () use ($checkId, $verified, $expiresOn, $note, $actorId) {
            $check = TeacherCheck::query()->whereKey($checkId)->lockForUpdate()->firstOrFail();
            $allowed = $verified ? $check->status === 'pending' : in_array($check->status, ['pending', 'verified'], true);
            if (! $allowed) {
                throw ValidationException::withMessages(['check' => __('people::checks.errors.decided')]);
            }
            if ($verified && $expiresOn !== null && $check->issued_on !== null && $expiresOn->toDateString() < $check->issued_on->toDateString()) {
                throw ValidationException::withMessages(['expires_on' => __('people::checks.errors.expires_before')]);
            }
            $check->update([
                'status' => $verified ? 'verified' : 'rejected',
                'expires_on' => $verified ? $expiresOn : $check->expires_on,
                'decided_by' => $actorId, 'decided_at' => CarbonImmutable::now(), 'decision_note' => $note,
            ]);
            $this->audit->record($verified ? 'people.check_verified' : 'people.check_rejected', 'user', $check->teacher_id, [
                'teacher' => (string) DB::table('users')->where('id', $check->teacher_id)->value('name'),
                'document' => $check->kind->value,
                'until' => $verified ? ($expiresOn?->toDateString() ?? '—') : null,
            ]);
            DB::afterCommit(fn () => event(new TeacherCheckDecided($check->academy_id, $check->id, $verified)));

            return $check;
        });
    }
}
