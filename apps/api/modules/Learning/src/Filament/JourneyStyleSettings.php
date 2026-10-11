<?php

namespace Modules\Learning\Filament;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Modules\Learning\Actions\SaveJourneyStyle;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Queries\Journey\JourneyMap;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The academy's choice of how its students see their journey (curriculum.manage):
 * playful (each in their stage's look, with islands and games), calm (levels and
 * missions, no islands) or plain (no stars either). Shown on the games' settings
 * page ("games::settings" hook), saved through SaveJourneyStyle.
 */
final class JourneyStyleSettings extends Component
{
    public const HOOK = 'games::settings';

    public string $style = 'playful';

    public function mount(): void
    {
        $this->style = LearningSettings::current()->journey_style;
    }

    public function save(): void
    {
        abort_unless(Gate::allows(Ability::ManageCurriculum->value), 403);
        app(SaveJourneyStyle::class)->handle($this->style);
        Notification::make()->success()->title(__('learning::map.settings.saved'))->send();
    }

    public function render(): View
    {
        return view('learning::filament.journey-style', ['styles' => JourneyMap::STYLES, 'allowed' => Gate::allows(Ability::ManageCurriculum->value)]);
    }
}
