<?php

namespace Modules\Learning\Contracts;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/**
 * What the Quran module adds to Learning's own screens and answers
 * (docs/architecture/02, section 26), which it binds while the academy's plan
 * has the Quran; without it (NoLearningQuran) reports and homework are
 * whole and simply ask nothing of the Quran. Homework keeps what it asks to
 * recite in homework.expected, which only this reads and checks.
 */
interface LearningQuran
{
    /** Whether a report's homework may ask what to recite now (the teacher's check of recited homework). */
    public function recitationOffered(): bool;

    /**
     * What a homework asks to recite, from the report's form (null: none), as homework.expected keeps it. What
     * the homework already asks ($current) stays as it is even while the check is off.
     *
     * @param  ?array<string, mixed>  $current
     * @return ?array<string, mixed>
     *
     * @throws ValidationException keyed homework.expected
     */
    public function recitationTarget(mixed $input, string $accepts, ?array $current): ?array;

    /**
     * What a homework asks to recite, as forms and answers show it.
     *
     * @param  ?array<string, mixed>  $expected  homework.expected
     * @return ?array{surah: int, from: int, to: int, title: string}
     */
    public function presentTarget(?array $expected): ?array;

    /**
     * What to suggest from the report's lessons (null: none).
     *
     * @param  list<string>  $nodeIds  in the report's order
     * @return ?array{surah: int, from: int, to: int, title: string}
     */
    public function targetFromLessons(array $nodeIds): ?array;

    /**
     * What to suggest from the words marked in the lesson (null: none).
     *
     * @return ?array{surah: int, from: int, to: int, title: string}
     */
    public function targetFromMistakes(string $sessionId): ?array;

    /**
     * Each recording's check against what the homework asks to recite, by submission, for the teacher alone.
     *
     * @param  ?array<string, mixed>  $expected  homework.expected
     * @return array<string, array{status: string, status_label: string, failure: ?string, summary: ?string, summary_label: ?string, verses: string|array{}, extra: string|array{}, heard: ?string, note: 'A help while marking, not a mark: the words are heard automatically and can be wrong, so listen to the recording yourself.'}> status: pending, ready, failed; note in the reader's words (the API document has always shown it in English)
     */
    public function checks(string $homeworkId, ?array $expected): array;

    /**
     * The report form's fields for what the homework asks to recite (state homework.recite, homework.expected.*).
     *
     * @param  ?array{surah: int, from: int, to: int, title: string}  $fromMistakes
     * @return list<mixed> the form's components
     */
    public function reportFields(?array $fromMistakes, ?string $suggestion): array;

    /**
     * In the teacher's homework modal: what it asks to recite.
     *
     * @param  ?array<string, mixed>  $expected
     */
    public function targetView(?array $expected): ?Htmlable;

    /** @param  ?array<string, mixed>  $check  one of checks() */
    public function checkView(?array $check): ?Htmlable;

    /**
     * On the teacher's page of a student: the way to the Quran's own page for the course (the hifz map and the
     * mistakes notebook), when it follows the course.
     *
     * @return list<mixed> header actions
     */
    public function studentActions(string $enrollmentId): array;
}
