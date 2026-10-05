<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
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
 */
final class User extends Authenticatable
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
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_managed_minor' => 'boolean',
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

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
