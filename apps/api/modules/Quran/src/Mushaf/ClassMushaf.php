<?php

namespace Modules\Quran\Mushaf;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Quran\Actions\Mushaf\MarkMistake;
use Modules\Quran\Actions\Mushaf\TurnMushafPage;
use Modules\Quran\Actions\Mushaf\UnmarkMistake;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Http\V1\Resources\MushafPresenter;
use Modules\Quran\Queries\Mushaf\LessonMushaf;
use Modules\Quran\Support\QuranArea;

/**
 * A lesson's shared mushaf as the class page reads and changes it (the same
 * page the apps read at GET learning/lessons/{session}/mushaf), for whoever
 * has it in that lesson (MushafLessons); nothing while the academy's plan
 * leaves the Quran out.
 */
final class ClassMushaf
{
    public function __construct(
        private readonly MushafLessons $lessons,
        private readonly LessonMushaf $mushaf,
        private readonly MushafPresenter $present,
        private readonly QuranApi $quran,
        private readonly QuranArea $area,
    ) {}

    /**
     * The page asked for, else the one the teacher has open, else where the lesson starts; null when the lesson has
     * no mushaf for this person.
     *
     * @return ?array<string, mixed>
     */
    public function page(string $sessionId, string $viewerId, ?int $page = null): ?array
    {
        if (! $this->area->on()) {
            return null;
        }
        $lesson = $this->lessons->for($sessionId, $viewerId);
        $shown = $lesson === null ? null : $this->mushaf->page($lesson, $page, CarbonImmutable::now());

        return $shown === null ? null : $this->present->page($shown);
    }

    /**
     * The lesson's teacher turns it ({page}), marks a word ({surah, verse, word, kind, page?}) or takes a mark off
     * ({surah, verse, word, page?}); answers the page as page() does.
     *
     * @param  array<string, mixed>  $input
     * @return ?array<string, mixed>
     *
     * @throws AuthorizationException not the lesson's teacher
     * @throws ValidationException a page or word not there, or words may not be marked now
     */
    public function change(string $sessionId, string $viewerId, string $change, array $input): ?array
    {
        if (! in_array($change, MushafInput::CHANGES, true)) {
            throw new InvalidArgumentException("No such change to a mushaf: {$change}");
        }
        $lesson = $this->area->on() ? $this->lessons->for($sessionId, $viewerId) : null;
        if ($lesson === null) {
            return null;
        }
        $data = Validator::make($input, MushafInput::rules($change), [], trans('quran::mushaf.attributes'))->validate();
        $at = fn (string $key) => (int) $data[$key];
        match ($change) {
            'turn' => app(TurnMushafPage::class)->handle($lesson, $at('page')),
            'mark' => app(MarkMistake::class)->handle($lesson, $at('surah'), $at('verse'), $at('word'), (string) $data['kind']),
            'unmark' => app(UnmarkMistake::class)->handle($lesson, $at('surah'), $at('verse'), $at('word')),
        };

        return $this->page($sessionId, $viewerId, MushafInput::pageOf($data, $this->quran));
    }
}
