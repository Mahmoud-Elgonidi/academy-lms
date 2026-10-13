<?php

namespace Modules\Identity\Actions\Staff;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\Events\UserInvited;
use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Staff\TeamGuard;

/**
 * Sends a new team member's welcome once more (their mail was not set up,
 * the message was lost), to their own email and number: only until they
 * chose a password, by someone ranked at least as high. People sends it
 * after commit.
 */
final class SendStaffWelcome
{
    public function __construct(private readonly TeamGuard $team) {}

    public function handle(string $userId, string $actorId): void
    {
        DB::transaction(function () use ($userId, $actorId) {
            $actor = $this->team->actor($actorId);
            $user = $this->team->member($userId);
            TeamGuard::refuse(StaffRules::whyNotTouch($actor->roles(), $actor->id === $user->id, $user->roles()), 'status');
            TeamGuard::refuse(match (true) {
                ! $user->isActive() => 'disabled',
                $user->password !== null => 'has_password',
                $user->email === null && $user->phone_e164 === null => 'no_contact',
                default => null,
            }, 'status');

            $id = $user->id;
            DB::afterCommit(fn () => event(new UserInvited($id, again: true)));
        });
    }
}
