<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Str;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditTrail;
use SensitiveParameter;

/**
 * In the current academy, every demo account (…@demo.test, an address that
 * can never be a real person's) takes the server's demo password again,
 * after it changed in .env; their apps sign in again (tokens go) and the
 * panels ask for the new password on the next request. One line in the
 * academy's activity log says how many; never a real account.
 */
final class ResetDemoPasswords
{
    public const DOMAIN = '@demo.test';

    public function __construct(private readonly IdentityApi $identity, private readonly AuditTrail $audit) {}

    public function handle(#[SensitiveParameter] string $password): int
    {
        $users = User::query()->whereRaw('lower(email) like ?', ['%'.self::DOMAIN])->get();
        foreach ($users as $user) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $this->identity->endSessions($user->id);
        }
        if ($users->isNotEmpty()) {
            $this->audit->record('identity.demo_passwords_reset', data: ['count' => $users->count()]);
        }

        return $users->count();
    }
}
