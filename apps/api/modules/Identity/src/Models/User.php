<?php

namespace Modules\Identity\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

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
 */
final class User extends Authenticatable implements FilamentUser, HasLocalePreference
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

    protected $hidden = ['password', 'remember_token'];

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
        ];
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
            'family' => $this->hasRole(Role::Student, Role::Guardian),
            default => array_filter($this->roles(), fn (Role $role) => $role->isStaff()) !== [],
        };
    }

    /** Their own private channel (PersonalChannel); the panels' pop-up notifications listen on it too. */
    public function receivesBroadcastNotificationsOn(): string
    {
        return PersonalChannel::name((string) $this->getKey());
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
