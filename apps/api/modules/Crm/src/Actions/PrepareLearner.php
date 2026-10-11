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
 * reused when it fits (a parent with another child here), refused when not.
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

        return DB::transaction(function () use ($lead) {
            $existing = DB::table('users')->where('phone_e164', $lead->phone_e164)->first(['id', 'name']);
            $roles = $existing ? DB::table('user_roles')->where('user_id', $existing->id)->pluck('role')->all() : [];
            $taken = fn () => throw ValidationException::withMessages(['phone_e164' => __('crm::leads.errors.phone_taken', ['name' => $existing->name])]);
            $new = fn (string $name, Role $role, bool $phone, ?string $username = null) => $this->identity->createUser(new NewUser(
                name: $name,
                roles: [$role],
                email: $phone ? $lead->email : null,
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
                $learnerId = $new((string) $lead->learner_name, Role::Student, false, 'learner.'.substr(str_replace('-', '', $lead->id), -12));
                $this->people->linkGuardian($contactId, $learnerId);
                if ($lead->learner_birth_year !== null) {
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
