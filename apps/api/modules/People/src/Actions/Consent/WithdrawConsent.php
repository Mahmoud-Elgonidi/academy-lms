<?php

namespace Modules\People\Actions\Consent;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Contracts\Events\ConsentWithdrawn;
use Modules\People\Models\GuardianConsent;
use Modules\People\Models\GuardianStudent;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A guardian takes their consent back (or the office does it at their
 * request, saying so in the note). The consent is marked, never removed; the
 * child is signed out of every app and browser and waits again, and the office
 * hears about it.
 */
final class WithdrawConsent
{
    public function __construct(
        private readonly IdentityApi $identity,
        private readonly AuditTrail $audit,
        private readonly TenantContext $tenants,
    ) {}

    /** @param  bool  $asGuardian  the guardian themself (they must be the child's); else the office, which says why */
    public function handle(string $studentId, string $byUserId, bool $asGuardian, ?string $note = null): GuardianConsent
    {
        if ($asGuardian && ! GuardianStudent::query()->where('guardian_id', $byUserId)->where('student_id', $studentId)->exists()) {
            throw new AuthorizationException;
        }
        $note = trim((string) $note) ?: null;
        if (! $asGuardian && ($note === null || mb_strlen($note) < RecordConsent::NOTE_MIN)) {
            throw ValidationException::withMessages(['note' => __('people::consent.errors.note', ['min' => RecordConsent::NOTE_MIN])]);
        }

        return DB::transaction(function () use ($studentId, $byUserId, $note) {
            $consent = GuardianConsent::query()->live()->where('student_id', $studentId)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['student' => __('people::consent.errors.none')]);
            $consent->forceFill(['withdrawn_at' => now(), 'withdrawn_by' => $byUserId, 'withdraw_note' => $note !== null ? mb_substr($note, 0, 300) : null])->save();
            $this->identity->endSessions($studentId);
            $names = DB::table('users')->whereIn('id', [$studentId, $byUserId])->pluck('name', 'id');
            $this->audit->record('people.consent_withdrawn', 'user', $studentId, [
                'student' => $names[$studentId] ?? '', 'guardian' => $names[$byUserId] ?? '',
            ]);
            $academy = $this->tenants->require()->id;
            DB::afterCommit(fn () => event(new ConsentWithdrawn($academy, $studentId, $consent->id, $byUserId)));

            return $consent;
        });
    }
}
