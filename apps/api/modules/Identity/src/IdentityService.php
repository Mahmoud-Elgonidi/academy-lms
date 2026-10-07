<?php

namespace Modules\Identity;

use Illuminate\Support\Facades\URL;
use Modules\Identity\Actions\CreateUser;
use Modules\Identity\Authorization\AbilityMatrix;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Http\Controllers\WelcomeController;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\User;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;

final class IdentityService implements IdentityApi
{
    public function __construct(
        private readonly CreateUser $createUser,
        private readonly TenantContext $tenants,
        private readonly TenantDirectory $directory,
    ) {}

    public function createUser(NewUser $user): string
    {
        return $this->createUser->handle($user)->id;
    }

    public function academyIdForToken(string $plainTextToken): ?string
    {
        $token = PersonalAccessToken::findToken($plainTextToken);

        if ($token === null || ($token->expires_at !== null && $token->expires_at->isPast())) {
            return null;
        }

        return $token->academy_id;
    }

    public function usersWithAbility(Ability $ability): array
    {
        $roles = array_map(fn (Role $r) => $r->value, AbilityMatrix::rolesFor($ability));

        return User::query()->where('status', 'active')
            ->whereHas('roleRecords', fn ($q) => $q->whereIn('role', $roles))
            ->orderBy('name')->pluck('id')->all();
    }

    public function welcomeLink(string $userId): string
    {
        $path = URL::temporarySignedRoute('identity.welcome', now()->addDays(WelcomeController::LINK_DAYS), ['user' => $userId], absolute: false);

        return $this->directory->baseUrl($this->tenants->require()).$path;
    }
}
