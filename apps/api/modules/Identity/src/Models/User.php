<?php

namespace Modules\Identity\Models;

use Carbon\CarbonImmutable;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Identity\Contracts\SignInGate;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\BelongsToTenant;
use SensitiveParameter;

/**
 * @property string $id
 * @property string $academy_id
 * @property string $name
 * @property ?string $email
 * @property ?string $phone_e164
 * @property ?string $username
 * @property ?string $locale
 * @property ?string $timezone
 * @property UserStatus $status
 * @property bool $is_managed_minor
 * @property array<string, string> $preferences
 * @property ?string $app_authentication_secret the authenticator app's secret, once set up (encrypted)
 * @property ?array<string> $app_authentication_recovery_codes hashed one-time recovery codes (encrypted)
 * @property ?CarbonImmutable $last_sign_in_at the person's own last sign-in (not the platform team's as them)
 */
final class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasLocalePreference
{
    use BelongsToTenant;
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'phone_e164', 'username', 'password',
        'locale', 'timezone', 'status', 'is_managed_minor',
    ];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    protected $attributes = [
        'status' => 'active',
        'is_managed_minor' => false,
        'preferences' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_managed_minor' => 'boolean',
            'preferences' => 'array',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'last_sign_in_at' => 'immutable_datetime',
        ];
    }

    /**
     * The second factor changes through Filament's own actions (set up, new
     * recovery codes, turn off, a recovery code used at sign-in) and the
     * platform team's reset; each change is in the activity log, and none
     * may happen while the platform team is signed in as this person.
     */
    protected static function booted(): void
    {
        self::updating(function (User $user): void {
            if (! $user->isDirty(['app_authentication_secret', 'app_authentication_recovery_codes'])) {
                return;
            }
            if (AuditActor::current()->onBehalfOf !== null) {
                throw new AuthorizationException(__('identity::two_factor.errors.impersonating'));
            }
        });
        self::updated(function (User $user): void {
            $secret = $user->wasChanged('app_authentication_secret');
            $codes = $user->wasChanged('app_authentication_recovery_codes');
            if (! $secret && ! $codes) {
                return;
            }
            $action = match (true) {
                $secret && filled($user->app_authentication_secret) => 'two_factor_enabled',
                $secret => 'two_factor_disabled',
                count($user->app_authentication_recovery_codes ?? []) === count((array) $user->getOriginal('app_authentication_recovery_codes')) - 1 => 'recovery_code_used',
                default => 'recovery_codes_renewed',
            };
            app(AuditTrail::class)->record('identity.'.$action, 'user', $user->id, ['name' => $user->name]);
        });
    }

    /** @return HasMany<UserRole, $this> */
    public function roleRecords(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /** @return list<Role> In the order Role declares them, so API output is stable. */
    public function roles(): array
    {
        $held = $this->roleRecords->map(fn (UserRole $r) => $r->role)->all();

        return array_values(array_filter(Role::cases(), fn (Role $role) => in_array($role, $held, true)));
    }

    public function hasRole(Role ...$roles): bool
    {
        return array_intersect(
            array_map(fn (Role $r) => $r->value, $roles),
            array_map(fn (Role $r) => $r->value, $this->roles()),
        ) !== [];
    }

    public function assignRole(Role $role): void
    {
        $this->roleRecords()->firstOrCreate(['role' => $role]);
        $this->unsetRelation('roleRecords');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * The academy back office is for active staff; the teacher area (/teach)
     * for active teachers; the family area (/my) for students and guardians.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return match ($panel->getId()) {
            'teach' => $this->hasRole(Role::Teacher),
            // A child whose guardian has not agreed yet (or took it back) stays out, even with a session from before.
            'family' => $this->hasRole(Role::Student, Role::Guardian) && app(SignInGate::class)->refusal((string) $this->getKey()) === null,
            default => array_filter($this->roles(), fn (Role $role) => $role->isStaff()) !== [],
        };
    }

    /** Their own private channel (PersonalChannel); the panels' pop-up notifications listen on it too. */
    public function receivesBroadcastNotificationsOn(): string
    {
        return PersonalChannel::name((string) $this->getKey());
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->twoFactorColumn('app_authentication_secret');
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    /** What the authenticator app shows under the academy's name. */
    public function getAppAuthenticationHolderName(): string
    {
        return $this->email ?? $this->phone_e164 ?? $this->username ?? $this->name;
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->twoFactorColumn('app_authentication_recovery_codes');
    }

    /** @param  ?array<string>  $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }

    /** A two-step column, read from the row when this model was built without it (made in memory, or a narrow select). */
    private function twoFactorColumn(string $column): mixed
    {
        if (! array_key_exists($column, $this->attributes)) {
            $this->attributes[$column] = $this->exists ? self::query()->whereKey($this->getKey())->value($column) : null;
            $this->syncOriginalAttribute($column);
        }

        return $this->getAttribute($column);
    }

    public function preferredLocale(): ?string
    {
        // A user built in memory may not carry the column yet; that means "no preference".
        return $this->attributes['locale'] ?? null;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
