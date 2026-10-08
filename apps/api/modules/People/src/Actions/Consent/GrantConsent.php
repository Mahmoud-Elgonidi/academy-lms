<?php

namespace Modules\People\Actions\Consent;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\People\Consent\ConsentRules;
use Modules\People\Contracts\Events\ConsentGranted;
use Modules\People\Models\GuardianConsent;
use Modules\People\Models\GuardianStudent;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A guardian agrees to their child's account, to the wording they were shown
 * (GuardianConsent::VERSION), from their own account or the link they were
 * sent; the address and browser are kept with it. Agreeing again while a
 * consent stands changes nothing.
 */
final class GrantConsent
{
    public function __construct(
        private readonly ConsentRules $rules,
        private readonly AuditTrail $audit,
        private readonly TenantContext $tenants,
    ) {}

    /** @param  string  $via  family (signed in) | link (the message's link) */
    public function handle(string $studentId, string $guardianId, string $via, ?string $ip = null, ?string $userAgent = null): GuardianConsent
    {
        if (! in_array($via, ['family', 'link'], true)) {
            throw new \InvalidArgumentException("Unknown way to agree [{$via}].");
        }
        if (! GuardianStudent::query()->where('guardian_id', $guardianId)->where('student_id', $studentId)->exists()) {
            throw new AuthorizationException;
        }
        if (! $this->rules->state($studentId)->needed) {
            throw ValidationException::withMessages(['student' => __('people::consent.errors.not_needed')]);
        }
        if ($live = GuardianConsent::liveFor($studentId)) {
            return $live;
        }

        try {
            return DB::transaction(function () use ($studentId, $guardianId, $via, $ip, $userAgent) {
                $consent = GuardianConsent::query()->create([
                    'student_id' => $studentId,
                    'guardian_id' => $guardianId,
                    'purpose' => 'account',
                    'version' => GuardianConsent::VERSION,
                    'via' => $via,
                    'ip' => $ip !== null ? Str::limit($ip, 45, '') : null,
                    'user_agent' => $userAgent !== null ? Str::limit($userAgent, 300, '') : null,
                    'granted_at' => now(),
                ]);
                $names = DB::table('users')->whereIn('id', [$studentId, $guardianId])->pluck('name', 'id');
                $this->audit->record('people.consent_granted', 'user', $studentId, [
                    'student' => $names[$studentId] ?? '', 'guardian' => $names[$guardianId] ?? '', 'via' => $via,
                ]);
                $academy = $this->tenants->require()->id;
                DB::afterCommit(fn () => event(new ConsentGranted($academy, $studentId, $consent->id, $via)));

                return $consent;
            });
        } catch (UniqueConstraintViolationException) {
            // Two guardians, or two taps, at once: the first one stands.
            return GuardianConsent::liveFor($studentId) ?? throw ValidationException::withMessages(['student' => __('people::consent.errors.try_again')]);
        }
    }
}
