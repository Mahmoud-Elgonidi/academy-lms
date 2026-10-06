<?php

namespace Modules\People\Filament\Family;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\People\Contracts\FamilyView;

/** At the top of every family page: whose account this is, and the other children to switch to. */
final class LearnerSwitcher extends Component
{
    public function choose(string $learnerId): void
    {
        app(FamilyView::class)->choose((string) auth()->id(), $learnerId);
        $this->js('window.location.reload()');
    }

    public function render(): View
    {
        $family = app(FamilyView::class);
        $viewer = (string) auth()->id();

        return view('people::family.switcher', [
            'learners' => $family->learners($viewer),
            'current' => $family->current($viewer),
        ]);
    }
}
