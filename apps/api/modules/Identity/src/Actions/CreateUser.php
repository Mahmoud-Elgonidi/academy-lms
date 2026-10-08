<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Queries\ActiveTeachers;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Creates a user, with roles, in the current academy. A new teacher needs
 * room in the academy's plan (its teacher limit).
 */
final class CreateUser
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AcademyPlan $plan,
        private readonly ActiveTeachers $teachers,
    ) {}

    public function handle(NewUser $data): User
    {
        $academyId = $this->tenants->require()->id;

        // Uniqueness is per academy; the RLS-scoped connection only sees this academy's rows.
        Validator::make([
            'name' => $data->name,
            'email' => $data->email,
            'phone_e164' => $data->phoneE164,
            'username' => $data->username,
            'roles' => $data->roles,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->where('academy_id', $academyId)],
            'phone_e164' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/', Rule::unique('users', 'phone_e164')->where('academy_id', $academyId)],
            'username' => ['nullable', 'regex:/^[a-z0-9._-]{3,40}$/i', Rule::unique('users', 'username')->where('academy_id', $academyId)],
            'roles' => ['required', 'array', 'min:1'],
        ])->after(function ($validator) use ($data): void {
            if ($data->email === null && $data->phoneE164 === null && $data->username === null) {
                $validator->errors()->add('email', __('identity::users.identifier_required'));
            }
        })->validate();

        return DB::transaction(function () use ($data, $academyId): User {
            if (in_array(Role::Teacher, $data->roles, true) && $this->plan->limit(Limit::Teachers) !== null) {
                // One new teacher at a time per academy, so two at once cannot both take the last place.
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ['plan:teachers:'.$academyId]);
                $this->plan->ensureRoom(Limit::Teachers, $this->teachers->count() + 1);
            }

            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'phone_e164' => $data->phoneE164,
                'username' => $data->username,
                'password' => $data->password,
                'locale' => $data->locale,
                'timezone' => $data->timezone,
                'is_managed_minor' => $data->isManagedMinor,
            ]);

            foreach (array_unique(array_map(fn ($r) => $r->value, $data->roles)) as $role) {
                $user->roleRecords()->create(['role' => $role]);
            }

            return $user->load('roleRecords');
        });
    }
}
