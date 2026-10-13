<?php

namespace Modules\Identity\SignIn;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;

/**
 * When someone last signed in, for the Team screen: every panel sign-in
 * (password, code, Google or Apple, the welcome link) through Laravel's
 * Login event, and every app or portal token (IssueAccessToken). The
 * platform team's sign-in as someone is theirs, not the person's.
 */
final class LastSignIn
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User || request()->routeIs('identity.impersonate')) {
            return;
        }
        self::stamp((string) $event->user->getKey());
    }

    /** The row only (no model events, no updated_at): a sign-in changes nothing else of the account. */
    public static function stamp(string $userId): void
    {
        DB::table('users')->where('id', $userId)->update(['last_sign_in_at' => now()]);
    }
}
