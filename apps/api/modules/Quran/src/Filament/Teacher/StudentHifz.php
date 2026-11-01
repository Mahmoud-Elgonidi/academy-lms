<?php

namespace Modules\Quran\Filament\Teacher;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Authorization\LearningAccess;
use Modules\Quran\Actions\Hifz\ClearHifzPages;
use Modules\Quran\Actions\Hifz\MarkMemorized;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Filament\Concerns\PlanActions;
use Modules\Quran\Hifz\HifzPages;
use Modules\Quran\Queries\Board\QuranCourses;
use Modules\Quran\Queries\Hifz\HifzMap;
use Modules\Quran\Queries\Hifz\StudentHifz as StudentHifzQuery;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * One student's hifz for their teacher (from the student's page): the map,
 * the last two weeks' reviews and the mistakes notebook, and saying which
 * pages the student already knows or taking pages off. Only on a course the
 * hifz map follows (HifzMap::follows).
 */
final class StudentHifz extends Page
{
    use PlanActions;

    protected string $view = 'quran::filament.hifz.student';

    protected static ?string $slug = 'students/{enrollment}/hifz';

    protected static bool $shouldRegisterNavigation = false;

    public string $enrollment = '';

    public string $studentId = '';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::TeachQuran->value);
    }

    public function mount(string $enrollment): void
    {
        $found = app(EnrollmentApi::class)->find($enrollment);
        abort_unless($found !== null && $found->teacherId === auth()->id() && app(LearningAccess::class)->canSeeEnrollment(auth()->user(), $found), 404);
        abort_unless(app(HifzMap::class)->follows($found), 404);
        $this->enrollment = $enrollment;
        $this->studentId = $found->studentId;
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::hifz.title').' · '.DB::table('users')->where('id', $this->studentId)->value('name');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function hifz(): array
    {
        return app(StudentHifzQuery::class)->for($this->studentId, CarbonImmutable::now(), app(ViewerTimezone::class)->effective());
    }

    /** @return array<string, mixed> the course's Quran plan */
    #[Computed]
    public function quran(): array
    {
        return app(QuranCourses::class)->course($this->enrollment, $this->studentId, CarbonImmutable::now());
    }

    protected function refreshPlans(): void
    {
        unset($this->quran);
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        $h = 'quran::hifz.';
        $choice = fn () => [
            Select::make('juz')->label(__($h.'attributes.juz'))->multiple()->native(false)
                ->options(collect(range(1, 30))->mapWithKeys(fn (int $j) => [$j => __($h.'juz', ['juz' => $j])])->all()),
            Select::make('surahs')->label(__($h.'attributes.surahs'))->multiple()->searchable()->native(false)
                ->options(collect(app(QuranApi::class)->surahs())->mapWithKeys(fn (array $s) => [$s['number'] => $s['number'].'. '.$s['name_ar'].' · '.$s['name_en']])->all()),
            TagsInput::make('pages')->label(__($h.'attributes.pages'))->placeholder('582'),
        ];
        $pages = fn (array $data) => app(HifzPages::class)->chosen(
            array_map('intval', $data['juz'] ?? []), array_map('intval', $data['surahs'] ?? []), array_map('intval', array_filter($data['pages'] ?? [], 'is_numeric')),
        );

        return [
            $this->adjustPlan(office: false)->label(__('quran::screens.plan.pace'))->arguments(['enrollment' => $this->enrollment])
                ->visible(fn () => $this->quran['plan'] !== null && ! $this->quran['plan']['group']),
            Action::make('hifzMark')->label(__($h.'mark.action'))->icon(Heroicon::OutlinedBookOpen)
                ->modalHeading(__($h.'mark.heading'))->modalDescription(__($h.'mark.hint'))
                ->schema([...$choice(), Radio::make('strength')->label(__($h.'attributes.strength'))->options(trans($h.'marks'))->default('good')->required()])
                ->action(function (array $data) use ($pages, $h): void {
                    $count = app(MarkMemorized::class)->handle($this->studentId, $this->picked($pages, $data), (string) $data['strength']);
                    unset($this->hifz);
                    Notification::make()->success()->title(trans_choice($h.'mark.done', $count, ['count' => $count]))->send();
                }),
            Action::make('hifzClear')->label(__($h.'clear.action'))->icon(Heroicon::OutlinedMinusCircle)->color('gray')
                ->visible(fn () => ($this->hifz['map']['counts']['memorized'] ?? 0) + ($this->hifz['map']['counts']['learning'] ?? 0) > 0)
                ->modalHeading(__($h.'clear.heading'))->modalDescription(__($h.'clear.hint'))
                ->schema($choice())
                ->action(function (array $data) use ($pages, $h): void {
                    $count = app(ClearHifzPages::class)->handle($this->studentId, $this->picked($pages, $data));
                    unset($this->hifz);
                    Notification::make()->success()->title(trans_choice($h.'clear.done', $count, ['count' => $count]))->send();
                }),
        ];
    }

    /**
     * The pages chosen in a form, or why not (under its fields).
     *
     * @return list<int>
     */
    private function picked(Closure $pages, array $data): array
    {
        try {
            $chosen = $pages($data);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ["mountedActions.0.data.{$k}" => $m])->all());
        }

        return $chosen !== [] ? $chosen : throw ValidationException::withMessages(['mountedActions.0.data.juz' => __('quran::hifz.errors.none')]);
    }
}
