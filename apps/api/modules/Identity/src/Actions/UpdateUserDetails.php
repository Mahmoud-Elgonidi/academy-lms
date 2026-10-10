<?php

namespace Modules\Identity\Actions;

use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Contracts\Events\ContactChanged;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Staff change a person's name, email, number, language or time zone. The
 * email and number are ways in (a sign-in code goes there), so a change to
 * either is in the audit log and the person hears of it (ContactChanged); a
 * person still needs one way in.
 */
final class UpdateUserDetails
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $userId, UserDetails $details): User
    {
        $academyId = $this->tenants->require()->id;
        $user = User::query()->findOrFail($userId);
        if ($user->status === UserStatus::Erased) {
            throw ValidationException::withMessages(['name' => __('identity::users.erased')]);
        }
        $email = $details->email === null || trim($details->email) === '' ? null : trim($details->email);

        Validator::make([
            'name' => trim($details->name),
            'email' => $email,
            'phone_e164' => $details->phoneE164,
            'locale' => $details->locale,
            'timezone' => $details->timezone,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->where('academy_id', $academyId)->ignore($user->id)],
            'phone_e164' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/', Rule::unique('users', 'phone_e164')->where('academy_id', $academyId)->ignore($user->id)],
            'locale' => ['nullable', 'in:ar,en'],
            'timezone' => ['nullable', Rule::in(DateTimeZone::listIdentifiers())],
        ])->after(function ($validator) use ($user, $email, $details): void {
            if ($email === null && $details->phoneE164 === null && $user->username === null) {
                $validator->errors()->add('email', __('identity::users.identifier_required'));
            }
        })->validate();

        $before = ['email' => $user->email, 'phone' => $user->phone_e164];
        $user->fill([
            'name' => trim($details->name),
            'email' => $email,
            'phone_e164' => $details->phoneE164,
            'locale' => $details->locale ?? $user->locale,
            'timezone' => $details->timezone ?? $user->timezone,
        ])->save();

        if ($before !== ['email' => $user->email, 'phone' => $user->phone_e164]) {
            // Which ways in changed, never the addresses themselves.
            $this->audit->record('identity.contact_changed', 'user', $user->id, [
                'name' => $user->name,
                'email' => $before['email'] !== $user->email,
                'phone' => $before['phone'] !== $user->phone_e164,
            ]);
            // The person hears it (a new address is a new way into their account), unless it was only cleared.
            foreach (['email' => $before['email'] !== $user->email && $user->email !== null, 'phone' => $before['phone'] !== $user->phone_e164 && $user->phone_e164 !== null] as $kind => $changed) {
                if ($changed) {
                    $id = $user->id;
                    DB::afterCommit(fn () => event(new ContactChanged($id, $kind, false)));
                }
            }
        }

        return $user;
    }
}
