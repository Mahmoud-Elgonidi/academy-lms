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
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Quran\Actions\Hifz\GradeHifzPage;
use Modules\Quran\Actions\Hifz\ReciteHifzPage;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Hifz\HifzPages;
use Modules\Quran\Http\V1\Resources\HifzPresenter;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Models\HifzRecitation;
use Modules\Quran\Models\RecitationMistake;
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
        return auth()->check() && app(QuranArea::class)->on() && FamilyQuran::learner() !== null && app(HifzPages::class)->count() > 0;
    }

    /** In the menu once the learner has pages on their map, or words marked in a lesson's mushaf. */
    public static function shouldRegisterNavigation(): bool
    {
        $learner = FamilyQuran::learner();

        return $learner !== null && app(QuranArea::class)->on() && (HifzPage::query()->where('student_id', $learner->id)->exists()
            || RecitationMistake::query()->where('student_id', $learner->id)->exists());
    }

    public static function getNavigationLabel(): string
    {
        return __('quran::hifz.nav.'.FamilyQuran::stage()->value);
    }

    public function getTitle(): string|Htmlable
    {
        $learner = FamilyQuran::learner();

        return $learner === null || $learner->isSelf
            ? __('quran::hifz.nav.'.FamilyQuran::stage()->value)
            : __('quran::hifz.title_of', ['name' => explode(' ', trim($learner->name))[0]]);
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
