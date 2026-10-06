<?php

namespace Modules\Billing\Filament\Family;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Time\ViewerTimezone;

/** Lessons left on each course of the learner on screen, and the invoices the person signed in pays. */
final class FamilyBilling extends Page
{
    protected string $view = 'billing::filament.family.billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'account';

    public static function getNavigationLabel(): string
    {
        return __('billing::family.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('billing::family.title');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function account(): array
    {
        $viewer = (string) auth()->id();
        $learner = app(FamilyView::class)->current($viewer);

        return [
            'learner' => $learner?->name,
            'balances' => $learner ? FamilyAccount::balances($learner->id) : [],
            'invoices' => FamilyAccount::invoices($viewer, app(ViewerTimezone::class)->effective()),
            'owed' => FamilyAccount::owed($viewer),
        ];
    }
}
