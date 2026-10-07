<?php

namespace Modules\Payroll\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Payroll\Actions\AddPayAdjustment;
use Modules\Payroll\Filament\Concerns\Attempts;
use Modules\Payroll\Filament\Concerns\PicksMonth;
use Modules\Payroll\Filament\PayrollView;
use Modules\Payroll\Models\RateCard;
use Modules\Payroll\Queries\PayrollMonth;
use Modules\Payroll\Queries\PayrollTeachers;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * One teacher's month: every paid lesson with how it was priced, the
 * corrections, and the bonuses and deductions, which can be added or taken
 * back here (docs/architecture/01, section 13).
 */
final class PayrollStatement extends Page
{
    use Attempts;
    use PicksMonth;

    protected string $view = 'payroll::filament.statement';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'payroll/{teacher}';

    public string $teacher;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManagePayroll->value);
    }

    public function mount(string $teacher): void
    {
        abort_unless(app(PayrollTeachers::class)->isTeacher($teacher), 404);
        $this->teacher = $teacher;
    }

    public function getTitle(): string|Htmlable
    {
        return __('payroll::payroll.statement.title', ['name' => app(PayrollTeachers::class)->name($this->teacher)]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $card = $this->card;

        return $card ? __('payroll::payroll.statement.card', ['card' => $card->name.' · '.$card->currency]) : __('payroll::payroll.statement.no_card');
    }

    public function getBreadcrumbs(): array
    {
        return [Payroll::getUrl(['month' => $this->monthStart()->format('Y-m')]) => __('payroll::payroll.statement.back')];
    }

    #[Computed]
    public function card(): ?RateCard
    {
        return RateCard::forTeacher($this->teacher);
    }

    /** @return list<array<string, mixed>> the month's totals, one per currency */
    #[Computed]
    public function totals(): array
    {
        return array_map(fn (array $r) => $r + ['hours' => PayrollView::hours($r['minutes'])], app(PayrollMonth::class)->teachers($this->monthStart(), $this->teacher));
    }

    /** @return array{lessons: list<array<string, mixed>>, adjustments: list<array<string, mixed>>} */
    #[Computed]
    public function lines(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $statement = app(PayrollMonth::class)->statement($this->monthStart(), $this->teacher);

        return [
            'lessons' => array_map(fn (array $r) => PayrollView::lesson($r, $tz), $statement['lessons']),
            'adjustments' => array_map(fn (array $r) => PayrollView::adjustment($r, $tz), $statement['adjustments']),
        ];
    }

    #[Computed]
    public function unpriced(): int
    {
        return app(PayrollMonth::class)->unpriced($this->monthStart(), $this->teacher)[$this->teacher] ?? 0;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function state(): array
    {
        return app(PayrollMonth::class)->state($this->monthStart());
    }

    protected function forgetMonth(): void
    {
        unset($this->totals, $this->lines, $this->unpriced, $this->state);
    }

    protected function getHeaderActions(): array
    {
        return [Payroll::adjustAction(fn () => $this->monthStart(), fn () => $this->teacher)->after(fn () => $this->forgetMonth())];
    }

    public function reverseAction(): Action
    {
        $l = 'payroll::payroll.statement.';

        return Action::make('reverse')->label(__($l.'reverse'))->link()->size('sm')->color('danger')
            ->requiresConfirmation()->modalHeading(__($l.'reverse_title'))->modalDescription(__($l.'reverse_hint'))
            ->action(function (array $arguments, Action $action) {
                $this->attempt($action, fn () => app(AddPayAdjustment::class)->reverse((string) ($arguments['entry'] ?? ''), (string) auth()->id()));
                $this->forgetMonth();
                Notification::make()->success()->title(__('payroll::payroll.actions.reversed'))->send();
            });
    }
}
