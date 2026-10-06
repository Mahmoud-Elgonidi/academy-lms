<?php

namespace Modules\Billing\Filament\Family;

use Filament\Widgets\Widget;
use Modules\People\Contracts\FamilyView;

/** Home: lessons left on each course, and what is owed when the person signed in pays. */
final class BalanceWidget extends Widget
{
    protected string $view = 'billing::filament.family.balance';

    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    /** Drawn with the page: no placeholder that jumps, no extra request. */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return FamilyAccount::shown();
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        $viewer = (string) auth()->id();
        $learner = app(FamilyView::class)->current($viewer);

        return ['balances' => $learner ? FamilyAccount::balances($learner->id) : [], 'owed' => FamilyAccount::owed($viewer)];
    }
}
