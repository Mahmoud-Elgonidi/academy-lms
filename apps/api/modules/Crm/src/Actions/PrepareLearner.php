<?php

namespace Modules\Crm\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Models\Lead;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\People\Contracts\PeopleApi;

/**
 * Gives a lead's learner (and, for a child, the parent) an account the
 * moment a trial or an enrolment needs one: a lesson has a student, and
 * messages and the class link go to people. No password: they join the
 * trial from their own signed link, without signing in (docs/architecture/
 * 01, section 8). An adult is the learner; a child gets an account under
 * the parent, who keeps the phone. A number already in the academy is
 * reused when it fits (a parent with another child here, and that child
 * when the name is the same: no second account for one child), refused
 * when not.
 * The parent is not asked for the child's consent yet: they bring the child
 * to the trial by their own link, and the consent is asked when the child
 * first needs a way in of their own (their sign-in, their seat, a course).
 */
final class PrepareLearner
{
    public function __construct(private readonly IdentityApi $identity, private readonly PeopleApi $people) {}

    /** @return string the learner's user id */
    public function handle(Lead $lead, ?string $actorId): string
    {
        if ($lead->learner_id !== null) {
            return $lead->learner_id;
        }
        // Its contact's account was erased (no number left): nobody to make an account for.
        if ($lead->phone_e164 === null) {
            throw ValidationException::withMessages(['phone_e164' => __('crm::leads.errors.no_phone')]);
        }

        // An email already another account's here is left off the new one (the number is the way in).
        $email = $lead->email !== null && ! DB::table('users')->where('email', $lead->email)->exists() ? $lead->email : null;

        return DB::transaction(function () use ($lead, $email) {
            $existing = DB::table('users')->where('phone_e164', $lead->phone_e164)->first(['id', 'name']);
            $roles = $existing ? DB::table('user_roles')->where('user_id', $existing->id)->pluck('role')->all() : [];
            $taken = fn () => throw ValidationException::withMessages(['phone_e164' => __('crm::leads.errors.phone_taken', ['name' => $existing->name])]);
            $new = fn (string $name, Role $role, bool $phone, ?string $username = null) => $this->identity->createUser(new NewUser(
                name: $name,
                roles: [$role],
                email: $phone ? $email : null,
                phoneE164: $phone ? $lead->phone_e164 : null,
                username: $username,
                locale: $lead->locale,
                timezone: $lead->timezone,
                isManagedMinor: ! $phone,
            ));

            if ($lead->for_child) {
                $contactId = match (true) {
                    $existing === null => $new($lead->contact_name, Role::Guardian, true),
                    in_array(Role::Guardian->value, $roles, true) => $existing->id,
                    default => $taken(),
                };
                $known = DB::table('guardian_student as g')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'g.academy_id')->on('u.id', '=', 'g.student_id'))
                    ->where('g.guardian_id', $contactId)->whereRaw('lower(trim(u.name)) = lower(trim(?))', [(string) $lead->learner_name])->value('u.id');
                $learnerId = $known ?? $new((string) $lead->learner_name, Role::Student, false, 'learner.'.substr(str_replace('-', '', $lead->id), -12));
                $this->people->linkGuardian($contactId, $learnerId, askConsent: false);
                if ($known === null && $lead->learner_birth_year !== null) {
                    $this->people->saveStudentProfile($learnerId, CarbonImmutable::create($lead->learner_birth_year, 7, 1), null);
                }
            } else {
                $learnerId = match (true) {
                    $existing === null => $new($lead->contact_name, Role::Student, true),
                    in_array(Role::Student->value, $roles, true) => $existing->id,
                    default => $taken(),
                };
                $contactId = $learnerId;
            }
            $lead->forceFill(['learner_id' => $learnerId, 'contact_id' => $contactId])->save();

            return $learnerId;
        });
    }
}
