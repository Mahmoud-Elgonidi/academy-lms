<?php

namespace Modules\Quran\Bridges;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Contracts\LearningQuran;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Filament\Teacher\StudentHifz;
use Modules\Quran\Http\V1\Resources\RecitationPresenter;
use Modules\Quran\Queries\Hifz\HifzMap;
use Modules\Quran\Recitation\RecitationTarget;
use Modules\Quran\Support\QuranArea;

/**
 * What the Quran adds to Learning's reports and homework (Learning's
 * LearningQuran): what recited homework asks to recite (homework.expected, a
 * passage by numbers), each recording's check for the teacher, the report
 * form's fields for it, and the way from a teacher's student page to the
 * course's hifz map. While the plan leaves the Quran out all of it answers
 * as if the module were not there (nothing shown, offered or checked), and
 * what a homework already asks stays as it is, untouched by any save.
 */
final class QuranLearning implements LearningQuran
{
    public function __construct(
        private readonly RecitationTarget $target,
        private readonly RecitationPresenter $present,
        private readonly QuranApi $quran,
        private readonly QuranArea $area,
    ) {}

    public function recitationOffered(): bool
    {
        return $this->target->offered();
    }

    public function recitationTarget(mixed $input, string $accepts, ?array $current): ?array
    {
        return $this->area->on() ? $this->target->from($input, $accepts, $current) : $current;
    }

    public function presentTarget(?array $expected): ?array
    {
        return $this->area->on() ? $this->target->present(VerseRange::fromArray($expected)) : null;
    }

    public function targetFromLessons(array $nodeIds): ?array
    {
        return $this->area->on() ? $this->target->suggestion($nodeIds) : null;
    }

    public function targetFromMistakes(string $sessionId): ?array
    {
        return $this->area->on() ? $this->target->fromMistakes($sessionId) : null;
    }

    public function checks(string $homeworkId, ?array $expected): array
    {
        return $this->area->on() ? $this->present->checks($homeworkId, $expected) : [];
    }

    public function reportFields(?array $fromMistakes, ?string $suggestion): array
    {
        if (! $this->area->on()) {
            return [];
        }
        $r = 'quran::recitation.';
        $surahs = $this->quran->surahs();
        $voice = fn (Get $get) => in_array($get('homework.accepts'), RecitationTarget::ACCEPTS, true);
        $on = fn (Get $get) => $voice($get) && (bool) $get('homework.recite');

        return [
            Toggle::make('homework.recite')->label(__($r.'toggle'))->helperText(__($r.'hint'))->live()->visible($voice)
                ->hintAction(Action::make('fromMistakes')->link()
                    ->label(__('quran::mushaf.from_mistakes', ['title' => $fromMistakes['title'] ?? '']))
                    ->visible($fromMistakes !== null)
                    ->action(function (Set $set) use ($fromMistakes): void {
                        $set('homework.recite', true);
                        foreach (['surah', 'from', 'to'] as $key) {
                            $set('homework.expected.'.$key, $fromMistakes[$key] ?? null);
                        }
                    })),
            Grid::make(3)->schema([
                Select::make('homework.expected.surah')->label(__($r.'fields.surah'))->native(false)->searchable()->live()
                    ->helperText($suggestion === null ? null : __($r.'suggestion', ['title' => $suggestion]))
                    ->options(collect($surahs)->mapWithKeys(fn (array $s) => [$s['number'] => $s['number'].'. '.$s['name_ar'].' · '.$s['name_en']])->all())
                    ->required($on),
                TextInput::make('homework.expected.from')->label(__($r.'fields.from'))->numeric()->minValue(1)->required($on),
                TextInput::make('homework.expected.to')->label(__($r.'fields.to'))->numeric()->minValue(1)
                    ->maxValue(fn (Get $get) => collect($surahs)->firstWhere('number', (int) $get('homework.expected.surah'))['verses'] ?? null)
                    ->required($on),
            ])->visible($on),
        ];
    }

    public function targetView(?array $expected): ?Htmlable
    {
        $shown = $this->presentTarget($expected);

        return $shown === null ? null : new HtmlString('<p style="margin:0; color:var(--gray-600)">'.e(__('quran::recitation.title')).' · <strong>'.e($shown['title']).'</strong></p>');
    }

    public function checkView(?array $check): ?Htmlable
    {
        return $check === null ? null : new HtmlString(view('quran::filament.teacher.recitation-check', ['check' => $check])->render());
    }

    public function studentActions(string $enrollmentId): array
    {
        $course = app(EnrollmentApi::class)->find($enrollmentId);
        if ($course === null || ! app(HifzMap::class)->follows($course)) {
            return [];
        }

        return [
            Action::make('quranHifz')->label(__('quran::hifz.title'))->icon(Heroicon::OutlinedBookOpen)
                ->url(StudentHifz::getUrl(['enrollment' => $enrollmentId], panel: 'teach')),
        ];
    }
}
