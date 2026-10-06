<?php

namespace Modules\Evaluation\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Evaluation\Actions\ExcludeLesson;
use Modules\Evaluation\Filament\KpiView;
use Modules\Evaluation\Models\KpiExclusion;
use Modules\Evaluation\Queries\PerformanceEvents;
use Modules\Evaluation\Queries\TeacherKpis;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * One teacher's KPIs (docs/architecture/02, section 7), what moved them
 * lately with the lesson behind each, and what families said with their
 * names. A lesson the platform or an outage spoiled is taken out of the
 * numbers here, with the reason; and put back.
 */
final class TeacherPerformance extends Page
{
    protected string $view = 'evaluation::filament.teacher-performance';

    protected static ?string $slug = 'teachers/{teacher}/performance';

    protected static bool $shouldRegisterNavigation = false;

    public string $teacherId;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ReviewPerformance->value);
    }

    public function mount(string $teacher): void
    {
        abort_unless(DB::table('user_roles')->where('user_id', $teacher)->where('role', 'teacher')->exists(), 404);
        $this->teacherId = $teacher;
    }

    public function getTitle(): string|Htmlable
    {
        return (string) DB::table('users')->where('id', $this->teacherId)->value('name');
    }

    public function getBreadcrumbs(): array
    {
        return [TeachersPerformance::getUrl() => __('evaluation::kpis.nav'), $this->getTitle()];
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function cards(): array
    {
        return KpiView::cards(app(TeacherKpis::class)->forTeachers([$this->teacherId])[$this->teacherId] ?? []);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function events(): array
    {
        $tz = app(ViewerTimezone::class)->effective();

        return array_map(fn (array $e) => KpiView::event($e, $tz), app(PerformanceEvents::class)->forTeacher($this->teacherId));
    }

    /** @return array{avg: ?string, count: int, list: list<array<string, mixed>>} */
    #[Computed]
    public function ratings(): array
    {
        return self::ratingsOf($this->teacherId, names: true);
    }

    public function excludeAction(): Action
    {
        $l = 'evaluation::kpis.';

        return Action::make('exclude')->label(__($l.'exclude'))->link()->size('sm')->color('gray')
            ->modalHeading(__($l.'exclude_heading'))->modalDescription(__($l.'exclude_hint'))->modalWidth('md')
            ->schema([
                Select::make('reason')->label(__($l.'reason'))->required()->native(false)
                    ->options(collect(KpiExclusion::REASONS)->mapWithKeys(fn (string $r) => [$r => __($l.'reasons.'.$r)])->all()),
                Textarea::make('note')->label(__($l.'note'))->rows(2)->maxLength(500),
            ])
            ->action(function (array $data, array $arguments) use ($l) {
                app(ExcludeLesson::class)->handle($this->ownLesson($arguments), $data['reason'], $data['note'] ?? null, (string) auth()->id());
                $this->forgetNumbers();
                Notification::make()->success()->title(__($l.'done'))->send();
            });
    }

    public function undoAction(): Action
    {
        return Action::make('undo')->label(__('evaluation::kpis.undo'))->link()->size('sm')->color('gray')
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                app(ExcludeLesson::class)->undo($this->ownLesson($arguments));
                $this->forgetNumbers();
                Notification::make()->success()->title(__('evaluation::kpis.done'))->send();
            });
    }

    /** @return array{avg: ?string, count: int, list: list<array<string, mixed>>} recent ratings, with the families' names only for managers */
    public static function ratingsOf(string $teacherId, bool $names): array
    {
        $locale = app()->getLocale();
        $tz = app(ViewerTimezone::class)->effective();
        $stats = DB::table('lesson_ratings')->where('teacher_id', $teacherId)->where('created_at', '>=', now()->subDays(TeacherKpis::DAYS))
            ->selectRaw('count(*) as n, avg(stars) as avg')->first();

        return [
            'avg' => $stats->n > 0 ? number_format((float) $stats->avg, 1) : null,
            'count' => (int) $stats->n,
            'list' => app(PerformanceEvents::class)->ratings($teacherId)->map(fn ($r) => [
                'stars' => (int) $r->stars,
                'tags' => array_map(fn (string $t) => __('evaluation::ratings.tags.'.$t), json_decode((string) $r->tags, true) ?: []),
                'comment' => $r->comment,
                'who' => $names ? __('evaluation::kpis.rated_by', ['name' => $r->rater]).' · '.$r->student : null,
                'lesson' => collect([$locale === 'ar' ? $r->subject_ar : $r->subject_en, CarbonImmutable::parse($r->starts_at)->setTimezone($tz)->locale($locale)->translatedFormat('j M')])->filter()->implode(' · '),
            ])->all(),
        ];
    }

    /** A lesson this teacher taught; anything else is refused. */
    private function ownLesson(array $arguments): string
    {
        $id = (string) ($arguments['session'] ?? '');
        abort_unless(DB::table('sessions')->where('id', $id)->where('teacher_id', $this->teacherId)->exists(), 404);

        return $id;
    }

    private function forgetNumbers(): void
    {
        unset($this->cards, $this->events);
    }
}
