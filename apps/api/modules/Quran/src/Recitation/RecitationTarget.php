<?php

namespace Modules\Quran\Recitation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Text\WordAlignment;

/**
 * What a homework asks the student to recite, for the teacher's check
 * (docs/architecture/02, section 22): a passage of the Quran by surah and
 * verses, on homework handed in by voice, while the academy has the helper
 * on. Its words always come from the platform's copy (QuranApi).
 */
final class RecitationTarget
{
    /** Homework that may come with a recording. */
    public const ACCEPTS = ['audio', 'any'];

    public function __construct(
        private readonly QuranApi $quran,
        private readonly AcademyAi $ai,
        private readonly QuranArea $area,
    ) {}

    /** Whether a teacher may ask for a check on homework now: the plan has the Quran, the helper is on, the text is here. */
    public function offered(): bool
    {
        return $this->area->on() && $this->ai->on(AiFeature::RecitationCheck) && $this->quran->surahs() !== [];
    }

    /**
     * What a homework asks to recite, from the form's {surah, from, to} (null: none), kept as a VerseRange.
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
        $fail = fn (string $error) => ValidationException::withMessages(['homework.expected' => __('quran::recitation.errors.'.$error)]);
        $values = Validator::make(['homework' => ['expected' => $input]], [
            'homework.expected' => ['array'],
            'homework.expected.surah' => ['required', 'integer', 'between:1,114'],
            'homework.expected.from' => ['required', 'integer', 'min:1'],
            'homework.expected.to' => ['required', 'integer', 'gte:homework.expected.from'],
        ], [], trans('quran::recitation.attributes'))->validate()['homework']['expected'];
        $source = VerseRange::of((int) $values['surah'], (int) $values['from'], (int) $values['to']);
        if ($current !== null && VerseRange::fromArray($current)?->toArray() === $source->toArray()) {
            return $source->toArray();
        }
        if (! in_array($accepts, self::ACCEPTS, true)) {
            throw $fail('not_audio');
        }
        if (! $this->area->on() || ! $this->ai->on(AiFeature::RecitationCheck)) {
            throw $fail('off');
        }
        $passage = $this->quran->passage($source) ?? throw $fail('verses');
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
    public function present(?VerseRange $source): ?array
    {
        $passage = $source === null ? null : $this->quran->passage($source);

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
        $sources = $this->quran->textSources($nodeIds);
        foreach ($nodeIds as $id) {
            if (isset($sources[$id])) {
                return $this->present($sources[$id]);
            }
        }

        return null;
    }

    /**
     * What to suggest from the words marked in the lesson's shared mushaf: the surah with the most marks, from its
     * first marked verse to its last (as many verses as a check takes).
     *
     * @return ?array{surah: int, from: int, to: int, title: string}
     */
    public function fromMistakes(string $sessionId): ?array
    {
        $top = DB::table('recitation_mistakes')->where('session_id', $sessionId)->groupBy('surah')
            ->selectRaw('surah, count(*) as marks, min(verse) as first_verse, max(verse) as last_verse')
            ->orderByDesc('marks')->orderBy('surah')->first();
        if ($top === null) {
            return null;
        }
        [$surah, $from, $to] = [(int) $top->surah, (int) $top->first_verse, (int) $top->last_verse];
        while ($to > $from && count($this->quran->passage(VerseRange::of($surah, $from, $to))?->words ?? []) > WordAlignment::MAX_EXPECTED) {
            $to--;
        }

        return $this->present(VerseRange::of($surah, $from, $to));
    }
}
