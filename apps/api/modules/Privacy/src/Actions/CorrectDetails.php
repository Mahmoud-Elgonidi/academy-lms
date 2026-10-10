<?php

namespace Modules\Privacy\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\PeopleApi;
use Modules\Privacy\PrivacyRules;
use Modules\Privacy\Queries\EditableDetails;
use Modules\Privacy\Subjects;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * A person puts their own details right, or a guardian their child's,
 * without asking the academy: the name, and a student's gender, country,
 * school grade and who should teach them (EditableDetails says which). Other
 * fields sent along are ignored. Saved through Identity's and People's own
 * contracts (their checks apply); the audit names the fields that changed,
 * never the values.
 */
final class CorrectDetails
{
    public function __construct(
        private readonly PrivacyRules $rules,
        private readonly EditableDetails $editable,
        private readonly IdentityApi $identity,
        private readonly PeopleApi $people,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * @param  array<string, ?string>  $changes  name, gender, country, school_grade, teacher_gender (null clears one, a name excepted)
     * @return list<string> the fields that changed
     */
    public function handle(string $subjectId, string $actorId, string $side, array $changes): array
    {
        if (! $this->rules->canAsk($actorId, $subjectId, $side)) {
            throw new AuthorizationException(__('privacy::requests.errors.not_yours'));
        }
        Subjects::ensureNotErased($subjectId);
        $changes = array_intersect_key($changes, array_flip($this->editable->fields($subjectId, $side)));
        $changes = array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v), $changes);

        return DB::transaction(function () use ($subjectId, $side, $changes) {
            $changed = [];
            if (array_key_exists('name', $changes) && $changes['name'] !== Subjects::name($subjectId)) {
                $this->identity->rename($subjectId, (string) $changes['name']);
                $changed[] = 'name';
            }

            $student = array_intersect_key($changes, array_flip(EditableDetails::STUDENT_FIELDS));
            if ($student !== []) {
                $now = $this->people->studentDetails($subjectId);
                $was = ['gender' => $now->gender, 'country' => $now->country, 'school_grade' => $now->schoolGrade, 'teacher_gender' => $now->teacherGender];
                $next = array_merge($was, $student);
                if (isset($next['country'])) {
                    $next['country'] = strtoupper($next['country']);
                }
                if ($next !== $was) {
                    $this->people->correctStudentDetails($subjectId, new StudentDetails($next['gender'], $next['country'], $next['school_grade'], $next['teacher_gender']));
                    $changed = [...$changed, ...array_keys(array_diff_assoc(array_map('strval', $next), array_map('strval', $was)))];
                }
            }

            if ($changed !== []) {
                $this->audit->record('privacy.details_corrected', 'user', $subjectId, ['fields' => $changed, 'side' => $side]);
            }

            return $changed;
        });
    }
}
