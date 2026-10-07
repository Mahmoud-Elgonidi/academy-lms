<?php

namespace Modules\Payroll\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Payroll\Actions\AddPayAdjustment;
use Modules\Payroll\Actions\ClosePayrollMonth;
use Modules\Payroll\Actions\ExportPayrollMonth;
use Modules\Payroll\Actions\RecalculatePayrollMonth;
use Modules\Payroll\Filament\Concerns\Attempts;
use Modules\Payroll\Filament\Concerns\PicksMonth;
use Modules\Payroll\Filament\PayrollView;
use Modules\Payroll\Filament\Resources\RateCards\RateCardResource;
use Modules\Payroll\Models\TeacherEarning;
use Modules\Payroll\Queries\PayrollMonth;
use Modules\Payroll\Queries\PayrollTeachers;
use Modules\Payroll\Support\PayrollMonths;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The month's payroll (docs/architecture/01, section 13): what each teacher
 * earned, their bonuses and deductions, lessons that should pay but have no
 * price yet, the export for whoever pays, and closing the month once it is
 * over. A row opens the teacher's statement.
 */
final class Payroll extends Page
{
    use Attempts;
    use PicksMonth;

    protected string $view = 'payroll::filament.payroll';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = -1;

    protected static ?string $slug = 'payroll';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManagePayroll->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('payroll::payroll.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('payroll::payroll.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('payroll::payroll.subtitle');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function rows(): array
    {
        $unpriced = $this->unpriced;
        $rows = array_map(fn (array $r) => $r + [
            'hours' => PayrollView::hours($r['minutes']),
            'unpriced' => $unpriced[$r['teacher_id']] ?? 0,
            'url' => PayrollStatement::getUrl(['teacher' => $r['teacher_id'], 'month' => $this->monthStart()->format('Y-m')]),
        ], app(PayrollMonth::class)->teachers($this->monthStart()));

        // Teachers with lessons to price and nothing paid yet still get a row, so they are not missed.
        $paid = array_column($rows, 'teacher_id');
        $names = app(PayrollTeachers::class)->options();
        foreach (array_diff(array_keys($unpriced), $paid) as $teacherId) {
            $rows[] = ['teacher_id' => $teacherId, 'name' => $names[$teacherId] ?? '—', 'currency' => null, 'lessons' => 0, 'hours' => '0', 'lesson_pay' => null, 'bonuses' => null,
                'deductions' => null, 'total' => null, 'unpriced' => $unpriced[$teacherId],
                'url' => PayrollStatement::getUrl(['teacher' => $teacherId, 'month' => $this->monthStart()->format('Y-m')])];
        }

        return $rows;
    }

    /** @return array<string, int> teacher => lessons with no price */
    #[Computed]
    public function unpriced(): array
    {
        return app(PayrollMonth::class)->unpriced($this->monthStart());
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function state(): array
    {
        return app(PayrollMonth::class)->state($this->monthStart());
    }

    /** @return array{totals: list<string>, teachers: int, lessons: int, hours: string} */
    #[Computed]
    public function tiles(): array
    {
        $paid = array_filter($this->rows, fn (array $r) => $r['total'] !== null);

        return [
            'totals' => array_map(fn ($m) => $m->format(), array_values(app(PayrollMonth::class)->totals($this->monthStart()))),
            'teachers' => count(array_unique(array_column($paid, 'teacher_id'))),
            'lessons' => array_sum(array_column($paid, 'lessons')),
            'hours' => PayrollView::hours(array_sum(array_column($paid, 'minutes'))),
        ];
    }

    public function priceMissing(): void
    {
        abort_unless(self::canAccess(), 403);
        $count = app(RecalculatePayrollMonth::class)->handle($this->monthStart(), reprice: false, actorId: (string) auth()->id());
        $this->forgetMonth();
        Notification::make()->success()->title(trans_choice('payroll::payroll.actions.recalculated', $count, ['count' => $count]))->send();
    }

    protected function forgetMonth(): void
    {
        unset($this->rows, $this->unpriced, $this->state, $this->tiles);
    }

    protected function getHeaderActions(): array
    {
        $l = 'payroll::payroll.';

        return [
            Action::make('rates')->label(__('payroll::rates.nav'))->icon(Heroicon::OutlinedTag)->color('gray')
                ->url(fn () => RateCardResource::getUrl()),
            self::adjustAction(fn () => $this->monthStart(), fn () => null)->after(fn () => $this->forgetMonth()),
            Action::make('recalc')->label(__($l.'actions.recalc'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn () => ! $this->state['closed'])
                ->requiresConfirmation()->modalDescription(__($l.'actions.recalc_hint'))
                ->action(function (Action $action) use ($l) {
                    $count = $this->attempt($action, fn () => app(RecalculatePayrollMonth::class)->handle($this->monthStart(), reprice: true, actorId: (string) auth()->id()));
                    $this->forgetMonth();
                    Notification::make()->success()->title(trans_choice($l.'actions.recalculated', (int) $count, ['count' => (int) $count]))->send();
                }),
            Action::make('export')->label(__($l.'actions.export'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(function () {
                    $export = app(ExportPayrollMonth::class);

                    return response()->download($export->handle($this->monthStart()), $export->filename($this->monthStart()))->deleteFileAfterSend();
                }),
            Action::make('close')->label(__($l.'actions.close'))->icon(Heroicon::OutlinedLockClosed)->color('primary')
                ->visible(fn () => $this->state['over'] && ! $this->state['closed'])
                ->requiresConfirmation()
                ->modalHeading(fn () => __($l.'actions.close_title', ['month' => $this->monthLabel()]))
                ->modalDescription(fn () => implode(' ', array_filter([
                    __($l.'actions.close_hint', ['month' => $this->monthLabel()]),
                    $this->state['pending'] > 0 ? __($l.'actions.close_pending', ['count' => $this->state['pending']]) : null,
                    $this->unpriced !== [] ? __($l.'actions.close_unpriced', ['count' => array_sum($this->unpriced)]) : null,
                ])))
                ->action(function (Action $action) use ($l) {
                    $this->attempt($action, fn () => app(ClosePayrollMonth::class)->handle($this->monthStart(), (string) auth()->id()));
                    $this->forgetMonth();
                    Notification::make()->success()->title(__($l.'actions.closed_done', ['month' => $this->monthLabel()]))->send();
                }),
        ];
    }

    /**
     * A bonus or a deduction, for the month on screen (or the first open one
     * after it); on a statement the teacher is already chosen.
     *
     * @param  Closure(): CarbonImmutable  $month
     * @param  Closure(): ?string  $teacher
     */
    public static function adjustAction(Closure $month, Closure $teacher): Action
    {
        $l = 'payroll::payroll.';

        return Action::make('adjust')->label(__($l.'actions.adjust'))->icon(Heroicon::OutlinedPlusCircle)->color('gray')
            ->modalWidth('md')
            ->modalDescription(fn () => __($l.'fields.month_hint', ['month' => PayrollMonths::label(app(PayrollMonths::class)->firstOpenFrom($month()))]))
            ->schema(fn () => array_values(array_filter([
                $teacher() === null ? Select::make('teacher')->label(__($l.'fields.teacher'))->required()->native(false)->searchable()
                    ->options(fn () => app(PayrollTeachers::class)->options()) : null,
                ToggleButtons::make('kind')->label(__($l.'fields.kind'))->inline()->required()->default('bonus')
                    ->options(collect(TeacherEarning::ADJUSTMENTS)->mapWithKeys(fn (string $k) => [$k => __($l.'kinds.'.$k)])->all())
                    ->colors(['bonus' => 'success', 'deduction' => 'danger']),
                TextInput::make('amount')->label(__($l.'fields.amount'))->numeric()->minValue(0.01)->required(),
                Textarea::make('note')->label(__($l.'fields.note'))->helperText(__($l.'fields.note_hint'))->rows(2)->maxLength(500)->required(),
            ])))
            ->action(function (array $data, Action $action) use ($month, $teacher, $l) {
                try {
                    $entry = app(AddPayAdjustment::class)->handle($teacher() ?? (string) $data['teacher'], (string) $data['kind'], (string) $data['amount'], (string) $data['note'], (string) auth()->id(), $month());
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__($l.'actions.adjusted', ['month' => PayrollMonths::label($entry->period)]))->send();
            });
    }
}
