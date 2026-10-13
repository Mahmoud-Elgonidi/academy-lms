<?php

namespace Modules\Identity\Actions\Staff;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Staff\TeamGuard;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * A new team member's own welcome link (the same signed link the welcome
 * message carries, on the academy's host, for WelcomeController::LINK_DAYS),
 * for the inviter to pass on themselves while the academy's mail is not set
 * up. Whoever holds it chooses the first password, so: only until one is
 * chosen, only by someone ranked at least as high, never while the platform
 * team is signed in as someone, and every showing is in the audit log
 * (the name only, never the link).
 */
final class StaffWelcomeLink
{
    public function __construct(
        private readonly TeamGuard $team,
        private readonly IdentityApi $identity,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $userId, string $actorId): string
    {
        return DB::transaction(function () use ($userId, $actorId) {
            $actor = $this->team->actor($actorId);
            $user = $this->team->member($userId);
            TeamGuard::refuse(StaffRules::whyNotTouch($actor->roles(), $actor->id === $user->id, $user->roles()), 'status');
            TeamGuard::refuse(match (true) {
                ! $user->isActive() => 'disabled',
                $user->password !== null => 'has_password',
                default => null,
            }, 'status');

            $this->audit->record('identity.welcome_link_shown', 'user', $user->id, ['name' => $user->name]);

            return $this->identity->welcomeLink($user->id);
        });
    }
}
