<?php

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;

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

    public function withRole(Role ...$roles): self
    {
        return $this->afterCreating(function (User $user) use ($roles): void {
            foreach ($roles as $role) {
                $user->assignRole($role);
            }
        });
    }

    public function disabled(): self
    {
        return $this->state(['status' => UserStatus::Disabled]);
    }
}
