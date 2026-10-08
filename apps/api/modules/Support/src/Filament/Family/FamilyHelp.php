<?php

namespace Modules\Support\Filament\Family;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\WithFileUploads;
use Modules\People\Contracts\FamilyView;
use Modules\Support\Filament\Concerns\RequesterTickets;
use Modules\Support\Queries\MyTickets;
use Modules\Support\SupportRules;

/**
 * "Help" in /my: a student or a guardian writes to the academy about the
 * learner on screen (a complaint about a lesson, a payment, a technical
 * problem, anything else) and follows the answers. Not in the kids' look
 * (their guardian writes); money only in the classic look.
 */
final class FamilyHelp extends Page
{
    use RequesterTickets;
    use WithFileUploads;

    protected string $view = 'support::filament.mine';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 85;

    protected static ?string $slug = 'help';

    public static function canAccess(): bool
    {
        return auth()->check() && app(FamilyView::class)->current((string) auth()->id()) !== null
            && app(SupportRules::class)->kindsFor('family', (string) auth()->id()) !== [];
    }

    public static function getNavigationLabel(): string
    {
        return __('support::tickets.mine.nav.'.app(FamilyView::class)->stage((string) auth()->id())->value);
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = app(MyTickets::class)->waitingOn((string) auth()->id());

        return $waiting > 0 ? (string) $waiting : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('support::tickets.mine.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('support::tickets.mine.subtitle');
    }

    protected function side(): string
    {
        return 'family';
    }

    protected function learnerId(): ?string
    {
        return app(FamilyView::class)->current((string) auth()->id())?->id;
    }
}
