<?php

namespace Modules\Scheduling\Http\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Scheduling\Actions\Disputes\DisputeLesson;
use Modules\Scheduling\Domain\Disputes\DisputeRules;

final class DisputeLessonRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** One of the lesson's dispute_reasons. */
            'reason' => ['required', 'string', 'in:'.implode(',', array_unique(array_merge(...array_values(DisputeRules::REASONS))))],
            /** What happened, in the person's words; the academy reads it. */
            'note' => ['required', 'string', 'max:'.DisputeLesson::NOTE_MAX],
            /** A group lesson: whose seat it is about (the teacher names one of group.students; a family, the learner when it follows several). */
            'student_id' => ['nullable', 'uuid'],
            /** The family's learner whose seat it is about, when the lesson has several it follows. */
            'learner_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
