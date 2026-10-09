<?php

namespace Modules\Safeguarding\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\FamilyView;
use Modules\Safeguarding\ConcernRules;
use Modules\Safeguarding\Enums\ConcernCategory;
use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\Safeguarding\Events\ConcernReported;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Models\ConcernEntry;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Someone raises a concern about a child's safety (docs/architecture/02,
 * section 15): a student about themselves (a child in the kids' look in a
 * child's words, with or without writing), a guardian about their child, a
 * teacher about a student they teach, or the office writing one down. It
 * may name a lesson from the last month (its teacher is then who it may be
 * about). Only the safeguarding leads read it; they hear at once, and a
 * lesson it names keeps its recordings while it is open. In the activity log
 * by number only.
 *
 * @param  string  $side  family | teacher | staff
 */
final class ReportConcern
{
    public function __construct(
        private readonly ConcernRules $rules,
        private readonly FamilyView $family,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(
        string $reporterId,
        string $side,
        ?string $studentId,
        string $category,
        ?string $body,
        bool $urgent = false,
        ?string $sessionId = null,
        ?string $teacherId = null,
    ): Concern {
        $reporterSide = match ($side) {
            'family' => $this->familySide($reporterId, $studentId),
            'teacher' => $this->rules->isTeacher($reporterId) ? 'teacher' : throw new AuthorizationException,
            'staff' => $this->rules->isLead($reporterId) ? 'staff' : throw new AuthorizationException,
            default => throw new AuthorizationException,
        };
        $look = $side === 'family' ? $this->rules->look($reporterId) : 'classic';
        $kind = ConcernCategory::tryFrom($category);
        if ($kind === null || ! in_array($kind, $this->rules->categoriesFor($side, $look), true)) {
            throw ValidationException::withMessages(['category' => __('safeguarding::concerns.errors.category')]);
        }
        $body = trim((string) $body);
        // A child may just press the button; anyone else says what happened.
        if (($look !== 'kids' && mb_strlen($body) < 3) || mb_strlen($body) > 5000) {
            throw ValidationException::withMessages(['body' => __('safeguarding::concerns.errors.body')]);
        }

        $lesson = null;
        if ($sessionId !== null && $sessionId !== '') {
            $lesson = $this->rules->linkableLesson($sessionId, $side, $studentId, $reporterId)
                ?? throw ValidationException::withMessages(['session' => __('safeguarding::concerns.errors.lesson')]);
            $studentId ??= $lesson->studentId;
        }
        if ($side === 'teacher') {
            // About a student they teach (or the lesson's), never a name typed in.
            if ($studentId !== null && $lesson?->studentId !== $studentId && ! array_key_exists($studentId, $this->rules->studentsOfTeacher($reporterId))) {
                throw ValidationException::withMessages(['student' => __('safeguarding::concerns.errors.student')]);
            }
            $teacherId = null;
        } elseif ($side === 'family') {
            $teacherId = $lesson?->teacherId;
        } else {
            $teacherId ??= $lesson?->teacherId;
        }

        return DB::transaction(function () use ($reporterId, $reporterSide, $studentId, $teacherId, $lesson, $kind, $body, $urgent) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['safeguarding:number:'.app(TenantContext::class)->require()->id]);
            $today = Concern::query()->where('reporter_id', $reporterId)->where('created_at', '>=', CarbonImmutable::now()->subDay())->count();
            if ($reporterSide !== 'staff' && $today >= ConcernRules::PER_DAY) {
                throw ValidationException::withMessages(['body' => __('safeguarding::concerns.errors.too_many')]);
            }
            $concern = Concern::query()->create([
                'number' => (int) Concern::query()->max('number') + 1,
                'reporter_id' => $reporterId, 'reporter_side' => $reporterSide,
                'student_id' => $studentId, 'teacher_id' => $teacherId, 'session_id' => $lesson?->id,
                'category' => $kind, 'body' => $body, 'urgent' => $urgent, 'status' => ConcernStatus::New,
            ]);
            ConcernEntry::event($concern, 'reported', $reporterId, ['side' => $reporterSide]);
            $this->audit->record('safeguarding.concern_reported', 'concern', $concern->id, ['concern' => $concern->reference(), 'category' => $kind->value]);
            DB::afterCommit(fn () => event(new ConcernReported($concern->academy_id, $concern->id)));

            return $concern;
        });
    }

    /** A family reports about the learner: themselves (a student) or a child they follow (a guardian). */
    private function familySide(string $reporterId, ?string $studentId): string
    {
        $learner = $studentId === null ? null : $this->family->learner($reporterId, $studentId);
        if ($learner === null || $learner->id !== $studentId) {
            throw new AuthorizationException;
        }

        return $learner->isSelf ? 'student' : 'guardian';
    }
}
