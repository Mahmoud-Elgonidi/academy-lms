<?php

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Identity\TwoFactor\TwoFactor;

/**
 * Creates users in the current academy (set a tenant first).
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'status' => UserStatus::Active,
            'timezone' => 'Africa/Cairo',
        ];
    }

    /** The authenticator app's secret test users carry (codes: TwoFactor::provider()->getCurrentCode()). */
    public const TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /**
     * Staff who must have two-step sign-in (the owner, admins, accountants)
     * come with it set up, as they are once past their first sign-in;
     * withoutTwoFactor() makes one who has not set it up yet.
     */
    public function withRole(Role ...$roles): self
    {
        return $this->afterCreating(function (User $user) use ($roles): void {
            foreach ($roles as $role) {
                $user->assignRole($role);
            }
            if (array_intersect(array_map(fn (Role $r) => $r->value, $roles), array_map(fn (Role $r) => $r->value, TwoFactor::REQUIRED_FOR)) !== []) {
                $user->forceFill(['app_authentication_secret' => self::TWO_FACTOR_SECRET])->saveQuietly();
            }
        });
    }

    public function withTwoFactor(): self
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['app_authentication_secret' => self::TWO_FACTOR_SECRET])->saveQuietly());
    }

    public function withoutTwoFactor(): self
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->saveQuietly());
    }

    public function disabled(): self
    {
        return $this->state(['status' => UserStatus::Disabled]);
    }
}
