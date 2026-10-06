<?php

namespace Modules\Crm\Filament\Teacher;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Billing\Contracts\BillingApi;
use Modules\Crm\Actions\AssessTrial;
use Modules\Crm\Filament\Concerns\Attempts;
use Modules\Crm\Models\LeadTrial;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * A teacher's trial lessons (docs/architecture/01, section 14, "تقييم
 * التجربة"): those waiting for their assessment first, then those coming
 * up, then the ones assessed. Assessing asks the level found, the plan and
 * lessons a week they suggest, and notes for the academy; the family does
 * not see it.
 */
final class MyTrials extends Page
{
    use Attempts;

    protected string $view = 'crm::filament.teacher.trials';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'trials';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::AssessTrials->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('crm::trials.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = LeadTrial::query()->where('teacher_id', auth()->id())->where('status', 'attended')->whereNull('assessed_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('crm::trials.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('crm::trials.subtitle');
    }

    /** @return array{to_assess: list<array<string, mixed>>, upcoming: list<array<string, mixed>>, done: list<array<string, mixed>>} */
    #[Computed]
    public function groups(): array
    {
        $now = CarbonImmutable::now();
        $viewer = app(ViewerTimezone::class)->effective();
        $locale = app()->getLocale();
        $plans = collect(app(BillingApi::class)->planOptions(null))->keyBy('id');
        $rows = LeadTrial::query()
            ->join('leads as l', fn ($j) => $j->on('l.academy_id', '=', 'lead_trials.academy_id')->on('l.id', '=', 'lead_trials.lead_id'))
            ->leftJoin('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'l.academy_id')->on('s.id', '=', 'l.subject_id'))
            ->where('lead_trials.teacher_id', auth()->id())
            ->where(fn ($q) => $q->where('lead_trials.status', 'booked')
                ->orWhere(fn ($q) => $q->where('lead_trials.status', 'attended')->where(fn ($q) => $q->whereNull('lead_trials.assessed_at')->orWhere('lead_trials.assessed_at', '>', $now->subDays(30)))))
            ->orderBy('lead_trials.starts_at')
            ->get(['lead_trials.*', 'l.contact_name', 'l.for_child', 'l.learner_name', 'l.learner_birth_year', 'l.notes as lead_notes', 's.name_ar as subject_ar', 's.name_en as subject_en']);

        $groups = ['to_assess' => [], 'upcoming' => [], 'done' => []];
        foreach ($rows as $t) {
            /** @var LeadTrial $t */
            $group = match (true) {
                $t->status === 'booked' => 'upcoming',
                $t->assessed_at === null => 'to_assess',
                default => 'done',
            };
            $groups[$group][] = [
                'id' => $t->id,
                'name' => $t->getAttribute('for_child') && $t->getAttribute('learner_name') ? $t->getAttribute('learner_name') : $t->getAttribute('contact_name'),
                'age' => $t->getAttribute('learner_birth_year') ? __('crm::trials.age', ['years' => $now->year - (int) $t->getAttribute('learner_birth_year')]) : null,
                'subject' => $locale === 'ar' ? $t->getAttribute('subject_ar') : $t->getAttribute('subject_en'),
                'when' => $t->starts_at->setTimezone($viewer)->locale($locale)->translatedFormat('l j F · H:i'),
                'notes' => $t->getAttribute('lead_notes'),
                'level' => $t->level,
                'plan' => $t->recommended_plan_id ? ($plans[$t->recommended_plan_id]->name ?? null) : null,
                'perWeek' => $t->recommended_per_week,
                'assessment' => $t->assessment,
            ];
        }
        $groups['done'] = array_reverse($groups['done']);

        return $groups;
    }

    public function assessAction(): Action
    {
        $l = 'crm::trials.';

        return Action::make('assess')
            ->label(fn (array $arguments) => __($l.(($arguments['again'] ?? false) ? 'reassess' : 'assess')))
            ->button()->size('sm')
            ->modalHeading(fn (array $arguments) => __($l.'assess_heading', ['name' => $arguments['name'] ?? '']))
            ->modalWidth('lg')
            ->fillForm(function (array $arguments) {
                $t = $this->ownTrial($arguments);

                return ['level' => $t->level, 'plan_id' => $t->recommended_plan_id, 'per_week' => $t->recommended_per_week, 'notes' => $t->assessment];
            })
            ->schema([
                Text::make(__($l.'assess_hint'))->color('gray'),
                TextInput::make('level')->label(__($l.'fields.level'))->placeholder(__($l.'level_hint'))->required()->maxLength(120),
                Select::make('plan_id')->label(__($l.'fields.plan_id'))->native(false)
                    ->options(fn () => collect(app(BillingApi::class)->planOptions(null))->mapWithKeys(fn ($p) => [$p->id => $p->name])->all()),
                Select::make('per_week')->label(__($l.'fields.per_week'))->native(false)
                    ->options(array_combine(range(1, 5), range(1, 5))),
                Textarea::make('notes')->label(__($l.'fields.notes'))->placeholder(__($l.'notes_hint'))->required()->rows(4)->maxLength(2000),
            ])
            ->action(function (array $data, array $arguments, Action $action) use ($l) {
                $this->attempt($action, fn () => app(AssessTrial::class)->handle($this->ownTrial($arguments), $data, (string) auth()->id()));
                unset($this->groups);
                Notification::make()->success()->title(__($l.'assessed'))->send();
            });
    }

    /** Only this teacher's own trial lessons. */
    private function ownTrial(array $arguments): LeadTrial
    {
        return LeadTrial::query()->where('teacher_id', auth()->id())->findOrFail($arguments['trial'] ?? null);
    }
}
