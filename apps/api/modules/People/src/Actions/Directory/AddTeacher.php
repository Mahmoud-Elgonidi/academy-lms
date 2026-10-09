<?php

namespace Modules\People\Actions\Directory;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\People\Actions\SaveTeacherProfile;
use Modules\People\Actions\SendWelcome;
use Modules\People\Contracts\DTOs\TeacherProfileData;

/**
 * A teacher added by hand: their account (the plan's teacher limit holds
 * at Identity's door) and profile together, and, if asked, the welcome
 * with their own link to choose a password.
 */
final class AddTeacher
{
    public function __construct(
        private readonly IdentityApi $identity,
        private readonly SaveTeacherProfile $profile,
        private readonly SendWelcome $welcome,
    ) {}

    public function handle(UserDetails $who, TeacherProfileData $profile, bool $welcome): string
    {
        return DB::transaction(function () use ($who, $profile, $welcome) {
            $id = $this->identity->createUser(new NewUser(
                name: $who->name, roles: [Role::Teacher], email: $who->email, phoneE164: $who->phoneE164, locale: $who->locale, timezone: $who->timezone,
            ));
            $this->profile->handle($profile->forTeacher($id));
            if ($welcome) {
                $this->welcome->handle([$id]);
            }

            return $id;
        });
    }
}
