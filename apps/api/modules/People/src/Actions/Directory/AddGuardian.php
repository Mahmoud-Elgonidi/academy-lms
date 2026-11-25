<?php

namespace Modules\People\Actions\Directory;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\People\Actions\SendWelcome;
use Modules\SharedKernel\Tenancy\AcademyAudience;

/**
 * A guardian added by hand (their children are added or linked from their
 * page), and, if asked, their welcome. Refused in an academy of adults only.
 */
final class AddGuardian
{
    public function __construct(
        private readonly IdentityApi $identity,
        private readonly SendWelcome $welcome,
        private readonly AcademyAudience $audience,
    ) {}

    public function handle(UserDetails $who, bool $welcome): string
    {
        if ($this->audience->adultsOnly()) {
            throw ValidationException::withMessages(['guardian' => __('people::directory.errors.adults_no_guardians')]);
        }

        return DB::transaction(function () use ($who, $welcome) {
            $id = $this->identity->createUser(new NewUser(name: $who->name, roles: [Role::Guardian], email: $who->email, phoneE164: $who->phoneE164, locale: $who->locale, timezone: $who->timezone));
            if ($welcome) {
                $this->welcome->handle([$id]);
            }

            return $id;
        });
    }
}
