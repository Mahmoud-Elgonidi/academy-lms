<?php

namespace Modules\Identity\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\SocialIdentity;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * The account itself: name, ways in, language and clock, roles, devices
 * signed in, and Google or Apple linked. Erasing it anonymises the row
 * (the lessons, invoices and messages that point at it stay, under
 * "Deleted account"): no name, email, number or password, a username that
 * says nothing, every sign-in ended, and it can never be turned on again.
 */
final class AccountData implements PersonalDataSource
{
    public function __construct(private readonly IdentityApi $identity, private readonly AuditTrail $audit) {}

    public function key(): string
    {
        return PersonalData::ACCOUNT;
    }

    public function export(string $userId): PersonalDataPart
    {
        $user = User::query()->with('roleRecords')->findOrFail($userId);

        return new PersonalDataPart([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone_e164,
            'username' => $user->username,
            'language' => $user->locale,
            'time_zone' => $user->timezone,
            'time_format' => $user->time_format,
            'roles' => $user->roles(),
            'status' => $user->status->value,
            'two_step_sign_in' => filled($user->getRawOriginal('app_authentication_secret')),
            'created_at' => $user->created_at?->toIso8601String(),
            'last_sign_in' => $user->last_sign_in_at?->toIso8601String(),
            'signed_in_devices' => PersonalAccessToken::query()->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)
                ->orderBy('created_at')->get(['name', 'created_at', 'last_used_at'])
                ->map(fn (PersonalAccessToken $t) => ['name' => $t->name, 'since' => $t->created_at?->toIso8601String(), 'last_used' => $t->last_used_at?->toIso8601String()])->all(),
            'sign_in_with' => SocialIdentity::query()->where('user_id', $user->id)->orderBy('linked_at')->get()
                ->map(fn (SocialIdentity $s) => ['provider' => $s->provider, 'email' => $s->email, 'linked_at' => $s->linked_at?->toIso8601String(), 'last_used' => $s->last_used_at?->toIso8601String()])->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        $user = User::query()->with('roleRecords')->findOrFail($userId);
        if (! $user->hasRole(Role::Owner)) {
            return [];
        }
        $owners = DB::table('user_roles')->join('users', fn ($j) => $j->on('users.academy_id', '=', 'user_roles.academy_id')->on('users.id', '=', 'user_roles.user_id'))
            ->where('user_roles.role', Role::Owner->value)->where('users.status', UserStatus::Active->value)->where('users.id', '!=', $userId)->count();

        return $owners === 0 ? [__('identity::privacy.last_owner')] : [];
    }

    public function erase(string $userId): array
    {
        $this->identity->endSessions($userId);
        $linked = SocialIdentity::query()->where('user_id', $userId)->delete();
        // The name they had leaves the academy's audit log too (what was done stays, by "Deleted account").
        $name = (string) User::query()->whereKey($userId)->value('name');
        $erased = __('identity::privacy.erased_name');
        $shared = User::query()->whereKeyNot($userId)->where('name', $name)->exists();
        $audit = $this->audit->forget($userId, $name, $erased, $shared);
        User::query()->whereKey($userId)->update([
            'name' => $erased,
            'email' => null,
            'phone_e164' => null,
            // A way in is required of every row; this one says nothing and leads nowhere (no password, never active).
            'username' => 'erased-'.substr(str_replace('-', '', $userId), 0, 12),
            'password' => null,
            'remember_token' => null,
            'timezone' => null,
            'time_format' => null,
            'status' => UserStatus::Erased->value,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
            'last_sign_in_at' => null,
            'updated_at' => now(),
        ]);

        return ['account' => 1, 'sign_in_with' => $linked, 'audit_names' => $audit];
    }
}
