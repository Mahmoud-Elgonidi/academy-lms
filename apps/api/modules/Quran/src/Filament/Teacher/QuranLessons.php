<?php

namespace Modules\Quran\Filament\Teacher;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Quran\Filament\Pages\QuranExamResult;
use Modules\Quran\Http\V1\Resources\ExamPresenter;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\QuranArea;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The teacher's Quran lessons (docs/architecture/02, section 26): the last
 * two days' lessons still to record and the coming week's, each opening
 * «حصة قرآن» with its portion; a course with no plan yet says the office
 * starts it; and the exams they examine, each opening its result page.
 */
final class QuranLessons extends Page
{
    public const BACK_DAYS = 2;

    public const AHEAD_DAYS = 7;

    protected string $view = 'quran::filament.teacher.lessons';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'quran/lessons';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::TeachQuran->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('quran::screens.lessons.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::screens.lessons.title');
    }

    public function getSubheading(): ?string
    {
        return __('quran::screens.lessons.subtitle');
    }

    /** @return array{today: list<array<string, mixed>>, later: list<array<string, mixed>>, record: list<array<string, mixed>>, exams: list<array<string, mixed>>} */
    #[Computed]
    public function lessons(): array
    {
        $me = (string) auth()->id();
        $now = CarbonImmutable::now();
        $tz = app(ViewerTimezone::class)->effective();
        $plans = app(Plans::class);
        $area = app(QuranArea::class);
        $enrollments = app(EnrollmentApi::class);
        $cards = app(AttendanceApi::class)->lessonsOf($me, $now->subDays(self::BACK_DAYS), $now->addDays(self::AHEAD_DAYS));
        $recorded = QuranLessonResult::query()->whereIn('session_id', array_map(fn (LessonCard $c) => $c->id, $cards))->distinct()->pluck('session_id')->flip();
        $today = $now->setTimezone($tz)->toDateString();

        $out = ['today' => [], 'later' => [], 'record' => [], 'exams' => []];
        foreach ($cards as $card) {
            if ($card->status === 'cancelled') {
                continue;
            }
            $plan = $plans->forLesson($card);
            $course = $card->enrollmentId === null ? null : $enrollments->find($card->enrollmentId);
            if ($plan === null && ($course === null || ! $area->isQuranSubject($course->subjectId))) {
                continue;
            }
            $start = $card->startsAt->setTimezone($tz);
            $row = [
                'id' => $card->id,
                'students' => implode('، ', array_map(fn (array $s) => $s['first_name'] ?? $s['name'], $card->students)) ?: $card->studentName,
                'group' => $card->isGroup(),
                'subject' => $card->subject(),
                'when' => $start->locale(app()->getLocale())->translatedFormat(Clock::pattern('l j M، H:i')),
                'time' => $start->locale(app()->getLocale())->translatedFormat(Clock::pattern('H:i')),
                'program' => $plan?->program->name,
                'has_plan' => $plan !== null,
                'recorded' => isset($recorded[$card->id]),
                'url' => QuranLesson::getUrl(['session' => $card->id]),
            ];
            $past = $card->endsAt->lessThan($now);
            match (true) {
                $past && ! $row['recorded'] && $plan !== null => $out['record'][] = $row,
                $past => null,
                $start->toDateString() === $today => $out['today'][] = $row,
                default => $out['later'][] = $row,
            };
        }
        if (Gate::any([Ability::ExamineQuran->value, Ability::TeachQuran->value])) {
            $present = app(ExamPresenter::class);
            $out['exams'] = QuranExam::query()->with('program')->where('examiner_id', $me)->where('status', 'booked')->get()
                ->map(fn (QuranExam $e) => $present->exam($e) + ['url' => QuranExamResult::getUrl(['exam' => $e->id])])
                ->sortBy(fn (array $e) => $e['lesson']['starts_at']['utc'] ?? '')->values()->all();
        }

        return $out;
    }
}
