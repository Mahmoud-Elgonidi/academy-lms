<?php

namespace Modules\People\Actions\Consent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\People\Consent\ConsentRules;
use Modules\People\Contracts\Events\ConsentGranted;
use Modules\People\Models\GuardianConsent;
use Modules\People\Models\GuardianStudent;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The office records a consent it holds outside the system: a signed form, a
 * call on a date. It says what it has (the note), and which guardian when the
 * guardian has an account; it is in the activity log under its name.
 */
final class RecordConsent
{
    public const NOTE_MIN = 10;

    public function __construct(
        private readonly ConsentRules $rules,
        private readonly AuditTrail $audit,
        private readonly TenantContext $tenants,
    ) {}

    public function handle(string $studentId, ?string $guardianId, string $note, string $staffId): GuardianConsent
    {
        $note = trim($note);
        Validator::make(['note' => $note], ['note' => ['required', 'string', 'min:'.self::NOTE_MIN, 'max:300']], [], ['note' => __('people::consent.attributes.note')])->validate();
        if ($guardianId !== null && ! GuardianStudent::query()->where('guardian_id', $guardianId)->where('student_id', $studentId)->exists()) {
            throw ValidationException::withMessages(['guardian' => __('people::consent.errors.not_their_guardian')]);
        }
        $state = $this->rules->state($studentId);
        if (! $state->needed) {
            throw ValidationException::withMessages(['student' => __('people::consent.errors.not_needed')]);
        }
        if ($state->granted) {
            throw ValidationException::withMessages(['student' => __('people::consent.errors.already')]);
        }

        return DB::transaction(function () use ($studentId, $guardianId, $note, $staffId) {
            $consent = GuardianConsent::query()->create([
                'student_id' => $studentId,
                'guardian_id' => $guardianId,
                'purpose' => 'account',
                'version' => GuardianConsent::VERSION,
                'via' => 'staff',
                'recorded_by' => $staffId,
                'note' => $note,
                'granted_at' => now(),
            ]);
            $this->audit->record('people.consent_recorded', 'user', $studentId, [
                'student' => (string) DB::table('users')->where('id', $studentId)->value('name'), 'note' => $note,
            ]);
            $academy = $this->tenants->require()->id;
            DB::afterCommit(fn () => event(new ConsentGranted($academy, $studentId, $consent->id, 'staff')));

            return $consent;
        });
    }
}
