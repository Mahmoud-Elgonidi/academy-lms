<?php

namespace Modules\Quran\Http\V1\Resources;

use Modules\Quran\Models\SubmissionCheck;
use Modules\SharedKernel\Ai\AiFailed;

/**
 * The teacher's own view of recited homework: what it asks to recite, and
 * each recording's check, word by word. Only teacher answers use it, never
 * the family's (LearningPresenter stays shared).
 */
final class RecitationPresenter
{
    /**
     * Each recording's check by submission (none while the homework asks nothing to recite).
     *
     * @param  ?array<string, mixed>  $expected  homework.expected
     * @return array<string, array<string, mixed>>
     */
    public function checks(string $homeworkId, ?array $expected): array
    {
        if ($expected === null) {
            return [];
        }

        return SubmissionCheck::query()->where('homework_id', $homeworkId)->get()->keyBy('submission_id')
            ->map(fn (SubmissionCheck $c) => $this->check($c))->all();
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
            'status_label' => __('quran::recitation.statuses.'.$check->status),
            // Why it could not be checked, in the reader's words.
            'failure' => $check->status === 'failed' ? (new AiFailed((string) $check->reason))->sayable() : null,
            'summary' => $result['summary'] ?? null,
            'summary_label' => isset($result['summary']) ? __('quran::recitation.summary', $result['summary']) : null,
            // Each verse's words: state ok, missing or wrong, and what was said instead.
            'verses' => $result['verses'] ?? [],
            // Words said that are not in the text, after the word they followed (counted from 1 through the passage).
            'extra' => $result['extra'] ?? [],
            'heard' => $check->heard,
            'note' => __('quran::recitation.note'),
        ];
    }
}
