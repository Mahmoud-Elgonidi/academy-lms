<?php

namespace Modules\People\Actions\Directory;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\People\Actions\SendWelcome;

/** A guardian added by hand (their children are added or linked from their page), and, if asked, their welcome. */
final class AddGuardian
{
    public function __construct(
        private readonly IdentityApi $identity,
        private readonly SendWelcome $welcome,
    ) {}

    public function handle(UserDetails $who, bool $welcome): string
    {
        return DB::transaction(function () use ($who, $welcome) {
            $id = $this->identity->createUser(new NewUser(name: $who->name, roles: [Role::Guardian], email: $who->email, phoneE164: $who->phoneE164, locale: $who->locale, timezone: $who->timezone));
            if ($welcome) {
                $this->welcome->handle([$id]);
            }

            return $id;
        });
    }
}
