<?php

namespace Modules\People\Actions\Family;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Enums\Role;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Actions\Directory\AddStudent;
use Modules\People\Actions\Directory\NewGuardian;
use Modules\People\Consent\ConsentRules;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Models\GuardianConsent;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Text\ArabicText;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * «إضافة طفل» (docs/architecture/02, section 27, J5a): a guardian adds their
 * own child from their account, then goes straight into the start flow. The
 * child is a managed minor under them (AddStudent: no sign-in of their own),
 * in the guardian's language, clock and country; the guardian's consent is
 * recorded at once (GrantConsent, `family`, to the wording's version they
 * saw), since the guardian is the one adding them, and no consent request
 * goes out. Only a guardian (403 otherwise); at most MAX_CHILDREN children
 * per guardian; the same name with the same birth year as one of theirs is
 * refused (the child is already there). One at a time per guardian (a lock),
 * so two taps never make two. The academy's plan counts a child only once a
 * course of theirs starts (StartOwnCourse, `academy_full`). Audited without
 * the name.
 */
final class AddOwnChild
{
    public const MAX_CHILDREN = 10;

    public function __construct(
        private readonly AddStudent $add,
        private readonly GrantConsent $consent,
        private readonly ConsentRules $rules,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * @param  ?CarbonImmutable  $birthDate  the day, when the guardian gave it
     * @param  ?int  $birthYear  else the year: the child is taken as born on its last day (the youngest they could be), so a minor's safeguards never fall short
     */
    public function handle(string $guardianId, string $name, ?CarbonImmutable $birthDate, ?int $birthYear, ?string $gender, int $consentVersion, ?string $ip = null, ?string $userAgent = null): string
    {
        if (! DB::table('user_roles')->where('user_id', $guardianId)->where('role', Role::Guardian->value)->exists()) {
            throw new AccessDeniedHttpException(__('people::family.add_child.errors.not_guardian'));
        }
        if ($consentVersion !== GuardianConsent::VERSION) {
            throw ValidationException::withMessages(['version' => __('people::consent.errors.changed')]);
        }
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $born = $birthDate ?? ($birthYear !== null ? CarbonImmutable::create($birthYear, 12, 31) : null);
        if ($born === null) {
            throw ValidationException::withMessages(['birth_year' => __('people::family.add_child.errors.birth')]);
        }
        if ($born->isFuture() || $born->lessThan(CarbonImmutable::now()->subYears(25))) {
            throw ValidationException::withMessages(['birth_year' => __('people::family.add_child.errors.birth_range')]);
        }

        return DB::transaction(function () use ($guardianId, $name, $born, $gender, $ip, $userAgent) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["add-child:{$guardianId}"]);
            $children = DB::table('guardian_student as gs')->join('users as u', 'u.id', '=', 'gs.student_id')
                ->leftJoin('student_profiles as sp', 'sp.user_id', '=', 'gs.student_id')
                ->where('gs.guardian_id', $guardianId)->get(['u.id', 'u.name', 'sp.birth_date']);
            if ($children->count() >= self::MAX_CHILDREN) {
                throw ValidationException::withMessages(['name' => trans_choice('people::family.add_child.errors.too_many', self::MAX_CHILDREN)]);
            }
            $same = $children->first(fn (object $c) => ArabicText::normalize(mb_strtolower((string) $c->name)) === ArabicText::normalize(mb_strtolower($name))
                && $c->birth_date !== null && substr((string) $c->birth_date, 0, 4) === $born->format('Y'));
            if ($same !== null) {
                throw ValidationException::withMessages(['name' => __('people::family.add_child.errors.duplicate', ['name' => $same->name])]);
            }

            $guardian = DB::table('users')->where('id', $guardianId)->first(['locale', 'timezone']);
            $country = DB::table('guardian_profiles')->where('user_id', $guardianId)->value('country');
            $childId = $this->add->handle(
                new UserDetails(name: $name, locale: $guardian->locale ?? null, timezone: $guardian->timezone ?? null),
                new NewGuardian(existingId: $guardianId),
                $born, null,
                new StudentDetails(gender: $gender, country: is_string($country) ? $country : null),
                welcome: false, askConsent: false,
            );
            if ($this->rules->state($childId)->needed) {
                $this->consent->handle($childId, $guardianId, 'family', $ip, $userAgent);
            }
            $this->audit->record('people.child_added', 'user', $childId);

            return $childId;
        });
    }
}
