<?php

namespace Modules\Learning\Assist;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Text\WordAlignment;

/**
 * What a homework asks the student to recite, for the teacher's check
 * (docs/architecture/02, section 22): a passage of the Quran by surah and
 * verses, on homework handed in by voice, while the academy has the helper
 * on. Its words always come from the platform's copy (CurriculumApi).
 */
final class RecitationTarget
{
    /** Homework that may come with a recording. */
    public const ACCEPTS = ['audio', 'any'];

    public function __construct(
        private readonly CurriculumApi $curriculum,
        private readonly AcademyAi $ai,
    ) {}

    /** Whether a teacher may ask for a check on homework now. */
    public function offered(): bool
    {
        return $this->ai->on(AiFeature::RecitationCheck) && $this->curriculum->surahs() !== [];
    }

    /**
     * What a homework asks to recite, from the form's {surah, from, to} (null: none), kept as a TextSource.
     * The same passage the homework already has stays even while the helper is off.
     *
     * @param  ?array<string, mixed>  $current  what the homework has now
     * @return ?array<string, mixed>
     *
     * @throws ValidationException
     */
    public function from(mixed $input, string $accepts, ?array $current = null): ?array
    {
        if ($input === null || $input === []) {
            return null;
        }
        $fail = fn (string $error) => ValidationException::withMessages(['homework.expected' => __('learning::recitation.errors.'.$error)]);
        $values = Validator::make(['homework' => ['expected' => $input]], [
            'homework.expected' => ['array'],
            'homework.expected.surah' => ['required', 'integer', 'between:1,114'],
            'homework.expected.from' => ['required', 'integer', 'min:1'],
            'homework.expected.to' => ['required', 'integer', 'gte:homework.expected.from'],
        ], [], trans('learning::recitation.attributes'))->validate()['homework']['expected'];
        $source = TextSource::quran((int) $values['surah'], (int) $values['from'], (int) $values['to']);
        if ($current !== null && TextSource::fromArray($current)?->toArray() === $source->toArray()) {
            return $source->toArray();
        }
        if (! in_array($accepts, self::ACCEPTS, true)) {
            throw $fail('not_audio');
        }
        if (! $this->ai->on(AiFeature::RecitationCheck)) {
            throw $fail('off');
        }
        $passage = $this->curriculum->passage($source) ?? throw $fail('verses');
        if (count($passage->words) > WordAlignment::MAX_EXPECTED) {
            throw $fail('too_long');
        }

        return $source->toArray();
    }

    /**
     * A passage as the forms show it.
     *
     * @return ?array{surah: int, from: int, to: int, title: string}
     */
    public function present(?TextSource $source): ?array
    {
        $passage = $source?->isQuran() ? $this->curriculum->passage($source) : null;

        return $passage === null ? null : ['surah' => (int) $source->surah, 'from' => (int) $source->from, 'to' => (int) $source->to, 'title' => $passage->title];
    }

    /**
     * What to suggest from the report's lessons: the first with a passage of the Quran for its text.
     *
     * @param  list<string>  $nodeIds  in the report's order
     * @return ?array{surah: int, from: int, to: int, title: string}
     */
    public function suggestion(array $nodeIds): ?array
    {
        $sources = $this->curriculum->textSources($nodeIds);
        foreach ($nodeIds as $id) {
            if (($sources[$id] ?? null)?->isQuran()) {
                return $this->present($sources[$id]);
            }
        }

        return null;
    }
}
