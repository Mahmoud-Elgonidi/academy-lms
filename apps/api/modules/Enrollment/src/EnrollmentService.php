<?php

namespace Modules\Enrollment;

use Carbon\CarbonImmutable;
use Modules\Enrollment\Actions\ActivateEnrollment;
use Modules\Enrollment\Actions\AttachCurriculum;
use Modules\Enrollment\Actions\CreateEnrollment;
use Modules\Enrollment\Actions\ReassignEnrollmentTeacher;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Models\Enrollment;

final class EnrollmentService implements EnrollmentApi
{
    public function __construct(
        private readonly CreateEnrollment $create,
        private readonly ActivateEnrollment $activate,
        private readonly ReassignEnrollmentTeacher $reassign,
        private readonly AttachCurriculum $curriculum,
    ) {}

    public function create(NewEnrollment $new): string
    {
        return $this->create->handle($new)->id;
    }

    public function attachCreditAccount(string $enrollmentId, string $creditAccountId): void
    {
        Enrollment::query()->whereKey($enrollmentId)->update(['credit_account_id' => $creditAccountId]);
    }

    public function activate(string $enrollmentId, ?string $actorId = null): void
    {
        $this->activate->handle($enrollmentId, $actorId);
    }

    public function reassignTeacher(string $enrollmentId, string $teacherId, CarbonImmutable $from, ?string $actorId = null): void
    {
        $this->reassign->handle($enrollmentId, $teacherId, $from, $actorId);
    }

    public function attachCurriculum(string $enrollmentId, ?string $programId, ?string $actorId = null): void
    {
        $this->curriculum->handle(Enrollment::query()->findOrFail($enrollmentId), $programId, $actorId);
    }

    public function find(string $enrollmentId): ?EnrollmentData
    {
        return Enrollment::query()->find($enrollmentId)?->toData();
    }

    public function forStudent(string $studentId): array
    {
        return Enrollment::query()->where('student_id', $studentId)->orderByDesc('created_at')->get()->map(fn (Enrollment $e) => $e->toData())->all();
    }

    public function forCreditAccount(string $creditAccountId): array
    {
        return Enrollment::query()->where('credit_account_id', $creditAccountId)->get()->map(fn (Enrollment $e) => $e->toData())->all();
    }
}
