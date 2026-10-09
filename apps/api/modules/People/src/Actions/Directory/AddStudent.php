<?php

namespace Modules\People\Actions\Directory;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\People\Actions\LinkGuardian;
use Modules\People\Actions\SaveStudentDetails;
use Modules\People\Actions\SaveStudentProfile;
use Modules\People\Actions\SendWelcome;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\LearnerStage;

/**
 * A student added by hand, as an import adds one: with their own email or
 * number they sign in themselves; without, they are a child under their
 * guardian (no sign-in of their own). The guardian is someone already here
 * or made now; linking asks them for consent when the child needs it.
 * The welcome goes, if asked, to whoever new can sign in.
 */
final class AddStudent
{
    public function __construct(
        private readonly IdentityApi $identity,
        private readonly LinkGuardian $link,
        private readonly SaveStudentProfile $profile,
        private readonly SaveStudentDetails $details,
        private readonly SendWelcome $welcome,
    ) {}

    public function handle(UserDetails $who, ?NewGuardian $guardian, ?CarbonImmutable $birthDate, ?LearnerStage $stage, StudentDetails $details, bool $welcome): string
    {
        $own = $who->email !== null || $who->phoneE164 !== null;
        if (! $own && ($guardian === null || ($guardian->existingId === null && $guardian->details === null))) {
            throw ValidationException::withMessages(['guardian' => __('people::directory.errors.child_needs_guardian')]);
        }

        return DB::transaction(function () use ($who, $guardian, $birthDate, $stage, $details, $welcome, $own) {
            $new = [];
            $guardianId = $guardian?->existingId;
            if ($guardian !== null && $guardianId === null && $guardian->details !== null) {
                $g = $guardian->details;
                $guardianId = $this->identity->createUser(new NewUser(name: $g->name, roles: [Role::Guardian], email: $g->email, phoneE164: $g->phoneE164, locale: $g->locale, timezone: $g->timezone));
                $new[] = $guardianId;
            } elseif ($guardianId !== null && ! DB::table('user_roles')->where('user_id', $guardianId)->where('role', Role::Guardian->value)->exists()) {
                throw ValidationException::withMessages(['guardian' => __('people::directory.errors.not_guardian')]);
            }

            $studentId = $this->identity->createUser(new NewUser(
                name: $who->name,
                roles: [Role::Student],
                email: $who->email,
                phoneE164: $who->phoneE164,
                username: $own ? null : 'learner.'.Str::lower(Str::random(12)),
                locale: $who->locale,
                timezone: $who->timezone,
                isManagedMinor: ! $own,
            ));
            if ($own) {
                $new[] = $studentId;
            }
            $this->profile->handle($studentId, $birthDate, $stage);
            $this->details->handle($studentId, $details);
            if ($guardianId !== null) {
                $this->link->handle($guardianId, $studentId, $guardian->relationship, true);
            }
            if ($welcome) {
                $this->welcome->handle($new);
            }

            return $studentId;
        });
    }
}
