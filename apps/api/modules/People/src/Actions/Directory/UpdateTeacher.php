<?php

namespace Modules\People\Actions\Directory;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Actions\SaveTeacherProfile;
use Modules\People\Contracts\DTOs\TeacherProfileData;

/** A teacher's details (Identity's, ways in audited) and profile, saved together. */
final class UpdateTeacher
{
    public function __construct(
        private readonly IdentityApi $identity,
        private readonly SaveTeacherProfile $profile,
    ) {}

    public function handle(string $teacherId, UserDetails $who, TeacherProfileData $profile): void
    {
        DB::transaction(function () use ($teacherId, $who, $profile) {
            $this->identity->updateUser($teacherId, $who);
            $this->profile->handle($profile->forTeacher($teacherId));
        });
    }
}
