<?php

namespace Modules\Crm\Filament\Teacher;

use BackedEnum;
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
use Modules\Crm\Queries\TeacherTrials;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Clock;
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
        $viewer = app(ViewerTimezone::class)->effective();
        $locale = app()->getLocale();

        return array_map(fn (array $group) => array_map(fn (array $t) => [
            'when' => $t['starts_at']->setTimezone($viewer)->locale($locale)->translatedFormat(Clock::pattern('l j F · H:i')),
            'perWeek' => $t['per_week'],
        ] + $t, $group), app(TeacherTrials::class)->of((string) auth()->id()));
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
