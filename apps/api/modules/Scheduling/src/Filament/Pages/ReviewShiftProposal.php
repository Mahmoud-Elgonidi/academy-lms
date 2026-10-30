<?php

namespace Modules\Scheduling\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Filament\Pages\ShiftProposals as ShiftProposalsPage;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The address a shift proposal's notifications open: the proposal now opens
 * in a slide-over on the proposals screen, so this one leads there.
 */
final class ReviewShiftProposal extends Page
{
    protected string $view = 'scheduling::filament.review-shift-proposal';

    protected static ?string $slug = 'shifts/proposals/{proposal}';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ReviewShifts->value);
    }

    public function mount(string $proposal): void
    {
        $id = ShiftProposal::query()->findOrFail($proposal)->id;
        $this->redirect(ShiftProposalsPage::getUrl(['proposal' => $id]));
    }
}
