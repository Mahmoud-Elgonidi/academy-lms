<?php

namespace Modules\Payroll\Filament\Teacher;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Payroll\Filament\Concerns\PicksMonth;
use Modules\Payroll\Filament\PayrollView;
use Modules\Payroll\Models\RateCard;
use Modules\Payroll\Models\RateCardLine;
use Modules\Payroll\Queries\PayrollMonth;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The teacher's own pay, month by month (docs/architecture/01, section 13:
 * "المدرس يشوف أرباحه"): every paid lesson with how it was priced, the
 * bonuses and deductions with their reasons, and their rates.
 */
final class MyEarnings extends Page
{
    use PicksMonth;

    protected string $view = 'payroll::filament.teacher.earnings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'earnings';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewOwnEarnings->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('payroll::payroll.mine.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('payroll::payroll.mine.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('payroll::payroll.mine.subtitle');
    }

    private function me(): string
    {
        return (string) auth()->id();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function totals(): array
    {
        return array_map(fn (array $r) => $r + ['hours' => PayrollView::hours($r['minutes'])], app(PayrollMonth::class)->teachers($this->monthStart(), $this->me()));
    }

    /** @return array{lessons: list<array<string, mixed>>, adjustments: list<array<string, mixed>>} */
    #[Computed]
    public function lines(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $statement = app(PayrollMonth::class)->statement($this->monthStart(), $this->me());

        return [
            'lessons' => array_map(fn (array $r) => PayrollView::lesson($r, $tz), $statement['lessons']),
            'adjustments' => array_map(fn (array $r) => PayrollView::adjustment($r, $tz), $statement['adjustments']),
        ];
    }

    #[Computed]
    public function unpriced(): int
    {
        return app(PayrollMonth::class)->unpriced($this->monthStart(), $this->me())[$this->me()] ?? 0;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function state(): array
    {
        return app(PayrollMonth::class)->state($this->monthStart());
    }

    /** @return list<array{what: string, price: string}> the teacher's own prices */
    #[Computed]
    public function rates(): array
    {
        $card = RateCard::forTeacher($this->me());
        if ($card === null) {
            return [];
        }
        $ar = str_starts_with(app()->getLocale(), 'ar');
        $subjects = DB::table('subjects')->pluck($ar ? 'name_ar' : 'name_en', 'id');

        return $card->lines->sortBy(fn (RateCardLine $l) => [$l->subject_id === null ? 0 : 1, (string) $l->session_type])->map(fn (RateCardLine $line) => [
            'what' => implode(' · ', array_filter([
                $line->subject_id ? ($subjects[$line->subject_id] ?? '') : null,
                $line->session_type ? __('payroll::payroll.types.'.$line->session_type) : null,
            ])) ?: __('payroll::payroll.mine.any'),
            'price' => __('payroll::payroll.units.'.$line->unit, ['amount' => Money::of($line->amount_minor, $card->currency)->format()]),
        ])->values()->all();
    }

    protected function forgetMonth(): void
    {
        unset($this->totals, $this->lines, $this->unpriced, $this->state);
    }
}
