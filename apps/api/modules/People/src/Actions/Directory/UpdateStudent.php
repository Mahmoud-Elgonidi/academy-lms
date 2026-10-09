<?php

namespace Modules\People\Actions\Directory;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Actions\SaveStudentDetails;
use Modules\People\Actions\SaveStudentProfile;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\LearnerStage;

/** A student's details (Identity's, ways in audited), age and look of their area, and profile, saved together. */
final class UpdateStudent
{
    public function __construct(
        private readonly IdentityApi $identity,
        private readonly SaveStudentProfile $profile,
        private readonly SaveStudentDetails $details,
    ) {}

    public function handle(string $studentId, UserDetails $who, ?CarbonImmutable $birthDate, ?LearnerStage $stage, StudentDetails $details): void
    {
        DB::transaction(function () use ($studentId, $who, $birthDate, $stage, $details) {
            $this->identity->updateUser($studentId, $who);
            $this->profile->handle($studentId, $birthDate, $stage);
            $this->details->handle($studentId, $details);
        });
    }
}
