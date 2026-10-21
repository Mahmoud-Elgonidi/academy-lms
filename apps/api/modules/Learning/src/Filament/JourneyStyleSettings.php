<?php

namespace Modules\Learning\Filament;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Modules\Learning\Actions\Hifz\SaveHifzDailyPages;
use Modules\Learning\Actions\SaveJourneyStyle;
use Modules\Learning\Hifz\HifzPages;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Queries\Journey\JourneyMap;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The academy's choice of how its students see their journey (curriculum.manage):
 * playful (each in their stage's look, with islands and games), calm (levels and
 * missions, no islands) or plain (no stars either); and, once the platform has
 * the Quran's text, how many pages the daily hifz review holds. Shown on the
 * games' settings page ("games::settings" hook), saved through SaveJourneyStyle
 * and SaveHifzDailyPages.
 */
final class JourneyStyleSettings extends Component
{
    public const HOOK = 'games::settings';

    public string $style = 'playful';

    public int|string $hifzPages = 3;

    public function mount(): void
    {
        $settings = LearningSettings::current();
        $this->style = $settings->journey_style;
        $this->hifzPages = $settings->hifz_daily_pages;
    }

    public function save(): void
    {
        abort_unless(Gate::allows(Ability::ManageCurriculum->value), 403);
        app(SaveJourneyStyle::class)->handle($this->style);
        Notification::make()->success()->title(__('learning::map.settings.saved'))->send();
    }

    public function saveHifz(): void
    {
        abort_unless(Gate::allows(Ability::ManageCurriculum->value), 403);
        $this->resetErrorBag('hifzPages');
        try {
            app(SaveHifzDailyPages::class)->handle((int) $this->hifzPages);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['hifzPages' => $e->errors()['hifz_daily_pages']]);
        }
        Notification::make()->success()->title(__('learning::hifz.settings.saved'))->send();
    }

    public function render(): View
    {
        return view('learning::filament.journey-style', [
            'styles' => JourneyMap::STYLES,
            'allowed' => Gate::allows(Ability::ManageCurriculum->value),
            'hifz' => app(HifzPages::class)->count() > 0,
            'max' => SaveHifzDailyPages::MAX,
        ]);
    }
}
