<?php

namespace Modules\Enrollment;

use Carbon\CarbonImmutable;
use Modules\Enrollment\Actions\ActivateEnrollment;
use Modules\Enrollment\Actions\AttachCurriculum;
use Modules\Enrollment\Actions\CreateEnrollment;
use Modules\Enrollment\Actions\EndCourse;
use Modules\Enrollment\Actions\PauseCourse;
use Modules\Enrollment\Actions\ReassignEnrollmentTeacher;
use Modules\Enrollment\Actions\ResumeCourse;
use Modules\Enrollment\Actions\SettleCourseDays;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Models\Enrollment;

final class EnrollmentService implements EnrollmentApi
{
    public function __construct(
        private readonly CreateEnrollment $create,
        private readonly ActivateEnrollment $activate,
        private readonly ReassignEnrollmentTeacher $reassign,
        private readonly AttachCurriculum $curriculum,
        private readonly PauseCourse $pause,
        private readonly ResumeCourse $resume,
        private readonly EndCourse $end,
        private readonly SettleCourseDays $settle,
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

    public function pause(string $enrollmentId, CarbonImmutable $from, CarbonImmutable $until, string $reason, CarbonImmutable $today, ?string $actorId = null): EnrollmentData
    {
        return $this->pause->handle($enrollmentId, $from, $until, $reason, $today, $actorId);
    }

    public function resume(string $enrollmentId, bool $early, ?string $actorId = null): EnrollmentData
    {
        return $this->resume->handle($enrollmentId, $early, $actorId);
    }

    public function end(string $enrollmentId, CarbonImmutable $on, string $reason, CarbonImmutable $today, ?string $actorId = null): EnrollmentData
    {
        return $this->end->handle($enrollmentId, $on, $reason, $today, $actorId);
    }

    public function settleDays(string $enrollmentId, CarbonImmutable $today): ?EnrollmentStatus
    {
        return $this->settle->handle($enrollmentId, $today);
    }

    public function withDays(): array
    {
        return Enrollment::query()->where(fn ($q) => $q->whereNotNull('paused_from')->orWhereNotNull('ends_on'))
            ->where('status', '<>', EnrollmentStatus::Ended->value)
            ->get()->map(fn (Enrollment $e) => $e->toData())->all();
    }

    public function stopReasons(string $enrollmentId): array
    {
        $row = Enrollment::query()->whereKey($enrollmentId)->first(['pause_reason', 'end_reason']);

        return ['pause' => $row?->pause_reason, 'end' => $row?->end_reason];
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
