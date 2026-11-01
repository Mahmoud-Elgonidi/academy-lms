<?php

namespace Modules\Quran\Filament\Family;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\People\Contracts\LearnerStage;
use Modules\Quran\Actions\Hifz\GradeHifzPage;
use Modules\Quran\Actions\Hifz\ReciteHifzPage;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Hifz\HifzPages;
use Modules\Quran\Http\V1\Resources\ExamPresenter;
use Modules\Quran\Http\V1\Resources\HifzPresenter;
use Modules\Quran\Http\V1\Resources\ProgramPresenter;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Models\HifzRecitation;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Models\RecitationMistake;
use Modules\Quran\Programs\JuzStrip;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Queries\Mushaf\MistakesBook;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The learner's hifz map and daily review (docs/architecture/02, section 22):
 * every page by part in its strength, and today's pages; a page opens to be
 * recited from memory, its text shown on asking, then either recorded and
 * checked (while the academy has the recitation check on) or graded by the
 * learner (or a guardian beside them). Below, the mistakes notebook: the
 * words the learner's teachers marked in lessons' mushaf, by surah. The kids'
 * look says it in a child's words. Follows the learner's hifz and mushaf
 * signals, so a check's result or a new mark shows as it comes.
 */
final class FamilyHifz extends Page
{
    use WithFileUploads;

    protected string $view = 'quran::filament.family.hifz';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 16;

    protected static ?string $slug = 'hifz';

    /** The page open in the review. */
    public ?int $open = null;

    /** Its text is shown (after reciting from memory). */
    public bool $revealed = false;

    /** The page recited, while it uploads. */
    public ?TemporaryUploadedFile $recording = null;

    public static function canAccess(): bool
    {
        $learner = auth()->check() && app(QuranArea::class)->on() ? FamilyQuran::learner() : null;

        return $learner !== null && (app(HifzPages::class)->count() > 0 || QuranPlan::query()->where('student_id', $learner->id)->where('status', 'active')->exists());
    }

    /** In the menu once the learner has a Quran plan, pages on their map, or words marked in a lesson's mushaf. */
    public static function shouldRegisterNavigation(): bool
    {
        $learner = FamilyQuran::learner();

        return $learner !== null && app(QuranArea::class)->on() && (HifzPage::query()->where('student_id', $learner->id)->exists()
            || RecitationMistake::query()->where('student_id', $learner->id)->exists()
            || QuranPlan::query()->where('student_id', $learner->id)->where('status', 'active')->exists());
    }

    public static function getNavigationLabel(): string
    {
        return __('quran::screens.family.nav.'.FamilyQuran::stage()->value);
    }

    public function getTitle(): string|Htmlable
    {
        $learner = FamilyQuran::learner();

        return $learner === null || $learner->isSelf
            ? __('quran::screens.family.nav.'.FamilyQuran::stage()->value)
            : __('quran::screens.family.title_of', ['name' => explode(' ', trim($learner->name))[0]]);
    }

    /**
     * The learner's Quran courses with a plan (the family's view: never a teacher's note; the kids' look without
     * counts), their exams and, when the academy shows them, their certificates.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function quran(): array
    {
        $learner = FamilyQuran::learner();
        $kids = FamilyQuran::stage() === LearnerStage::Kids;
        $plans = app(Plans::class);
        $present = app(ProgramPresenter::class);
        $exams = app(ExamPresenter::class);
        $strip = app(JuzStrip::class);
        $memorized = HifzPage::query()->where('student_id', $learner->id)->where('state', 'memorized')->pluck('page')->map(fn ($p) => (int) $p)->all();
        $subjects = app(CatalogApi::class)->subjectNames();
        $area = app(QuranArea::class);

        $courses = [];
        foreach (app(EnrollmentApi::class)->forStudent($learner->id) as $course) {
            $plan = $area->isQuranSubject($course->subjectId) ? $plans->forEnrollment($course->id) : null;
            if ($plan === null) {
                continue;
            }
            $courses[] = [
                'subject' => $subjects[$course->subjectId] ?? '',
                'plan' => $present->plan($plan, $plans->portion($plan), $plans->frozen($plan)),
                'juz' => $strip->juzOf($plan->position()),
                'recent' => QuranLessonResult::query()->where('plan_id', $plan->id)->where('student_id', $learner->id)->orderByDesc('recorded_at')->limit(5)->get()
                    ->reverse()->values()->map(fn (QuranLessonResult $r) => $present->result($r, family: true, counts: ! $kids))->all(),
            ];
        }
        $settings = QuranSettings::current();

        return [
            'kids' => $kids,
            'courses' => $courses,
            'shares' => $strip->shares($memorized),
            'parts' => $strip->count(),
            'exams' => QuranExam::query()->with('program')->where('student_id', $learner->id)->where('status', '<>', 'cancelled')->orderByDesc('created_at')->limit(6)->get()
                ->map(fn (QuranExam $e) => $exams->exam($e, family: true, counts: ! $kids))->all(),
            'certificates' => ! $settings->certificates_family ? [] : QuranCertificate::query()->where('student_id', $learner->id)->whereNull('revoked_at')->latest('issued_at')->get()
                ->map(fn (QuranCertificate $c) => $exams->certificate($c, (string) auth()->id()))->all(),
        ];
    }

    /** A grade as the kids' look shows it: stars out of three. */
    public static function stars(string $grade): int
    {
        return match ($grade) {
            'excellent' => 3,
            'very_good', 'good' => 2,
            'acceptable' => 1,
            default => 0,
        };
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function hifz(): array
    {
        $learner = FamilyQuran::learner();
        $now = CarbonImmutable::now();
        $present = app(HifzPresenter::class);
        $latest = HifzRecitation::query()->where('student_id', $learner->id)->where('created_at', '>=', $now->subDay())->latest('created_at')->first();
        $page = $this->open === null ? null : app(QuranApi::class)->quranPage($this->open);

        return [
            'mode' => app(AcademyAi::class)->on(AiFeature::RecitationCheck) ? 'check' : 'self',
            'map' => $present->map($learner->id, $now),
            'wird' => $present->wird($learner->id, $now),
            'page' => $page === null ? null : $present->page($page, HifzPage::query()->where('student_id', $learner->id)->where('page', $page->page)->first(), $now),
            'latest' => $latest === null ? null : ['heard' => null] + $present->recitation($latest),
            'book' => app(MistakesBook::class)->for($learner->id, $now),
            'tz' => app(ViewerTimezone::class)->effective(),
            'channel' => PersonalChannel::name((string) auth()->id()),
        ];
    }

    /** The page's words, in a child's words in the kids' look where it has its own. */
    public function word(string $key, array $replace = []): string
    {
        $kids = FamilyQuran::stage()->value === 'kids' && Lang::has('quran::hifz.kids.'.$key);

        return __('quran::hifz.'.($kids ? 'kids.' : '').$key, $replace);
    }

    public function openPage(int $page): void
    {
        $this->open = app(HifzPages::class)->exists($page) ? $page : null;
        $this->revealed = false;
        unset($this->hifz);
    }

    public function closePage(): void
    {
        $this->open = null;
        unset($this->hifz);
    }

    public function reveal(): void
    {
        $this->revealed = true;
    }

    public function grade(int $grade): void
    {
        $learner = FamilyQuran::learner();
        abort_if($learner === null || $this->open === null, 404);
        app(GradeHifzPage::class)->handle($learner->id, $this->open, $grade);
        Notification::make()->success()->title(__('quran::hifz.graded', ['grade' => $this->word('grades.'.$grade)]))->send();
        $this->closePage();
    }

    public function recite(): void
    {
        $learner = FamilyQuran::learner();
        abort_if($learner === null || $this->open === null, 404);
        try {
            app(ReciteHifzPage::class)->handle($learner->id, $this->open, $this->recording, (string) auth()->id());
        } catch (AiFailed $e) {
            Notification::make()->warning()->title($e->sayable())->send();

            return;
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        } finally {
            $this->recording = null;
        }
        $this->closePage();
    }
}
