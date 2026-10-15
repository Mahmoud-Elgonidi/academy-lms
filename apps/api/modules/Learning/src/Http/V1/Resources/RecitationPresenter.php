<?php

namespace Modules\Learning\Http\V1\Resources;

use Illuminate\Support\Collection;
use Modules\Learning\Assist\RecitationTarget;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\HomeworkSubmission;
use Modules\Learning\Models\SubmissionCheck;
use Modules\SharedKernel\Ai\AiFailed;

/**
 * The teacher's own view of recited homework: what it asks to recite, and
 * each recording's check, word by word. Only teacher answers use it, never
 * the family's (LearningPresenter stays shared).
 */
final class RecitationPresenter
{
    public function __construct(
        private readonly RecitationTarget $target,
        private readonly LearningPresenter $present,
    ) {}

    /**
     * The homework as LearningPresenter gives it, with what it asks to recite and each recording's check.
     *
     * @param  array<string, mixed>  $presented
     * @return array<string, mixed>
     */
    public function homework(Homework $h, array $presented): array
    {
        $checks = $this->checksOf($h);
        $presented['expected'] = $this->target->present($h->expectedSource());
        $presented['submissions'] = $h->submissions->map(fn (HomeworkSubmission $s) => [
            ...$this->present->submission($s),
            // The recording's check against what it asks to recite (null: none).
            'check' => $this->check($checks->get($s->id)),
        ])->values()->all();

        return $presented;
    }

    /**
     * Each recording's check by submission (none while the homework asks nothing to recite).
     *
     * @return array<string, array<string, mixed>>
     */
    public function checks(Homework $h): array
    {
        return $this->checksOf($h)->map(fn (SubmissionCheck $c) => $this->check($c))->all();
    }

    /** @return Collection<string, SubmissionCheck> by submission */
    private function checksOf(Homework $h): Collection
    {
        return $h->expected === null ? collect() : SubmissionCheck::query()->where('homework_id', $h->id)->get()->keyBy('submission_id');
    }

    /** @return ?array<string, mixed> */
    public function check(?SubmissionCheck $check): ?array
    {
        if ($check === null) {
            return null;
        }
        $result = $check->result ?? [];

        return [
            // pending, ready, failed
            'status' => $check->status,
            'status_label' => __('learning::recitation.statuses.'.$check->status),
            // Why it could not be checked, in the reader's words.
            'failure' => $check->status === 'failed' ? (new AiFailed((string) $check->reason))->sayable() : null,
            'summary' => $result['summary'] ?? null,
            'summary_label' => isset($result['summary']) ? __('learning::recitation.summary', $result['summary']) : null,
            // Each verse's words: state ok, missing or wrong, and what was said instead.
            'verses' => $result['verses'] ?? [],
            // Words said that are not in the text, after the word they followed (counted from 1 through the passage).
            'extra' => $result['extra'] ?? [],
            'heard' => $check->heard,
            'note' => __('learning::recitation.note'),
        ];
    }
}
