<?php

namespace Modules\Games\Filament\Family;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Games\Actions\SaveGameLimit;
use Modules\Games\Models\GameLimit;
use Modules\Games\Models\GameSettings;
use Modules\Games\Queries\GuardianGlance;
use Modules\Learning\Contracts\LearningApi;
use Modules\People\Contracts\FamilyView;

/**
 * A guardian's part of the games on their child's journey (the approved design,
 * Guardian), in the journey's guardian hook: today's play time against the
 * limit with the minutes a day and the night lock, what the child practised
 * this week, a creature hatched today to cheer, and the safety promise. Pay
 * and KPIs never appear here.
 */
final class JourneyGuardian extends Component
{
    public function learnerId(): ?string
    {
        return app(FamilyView::class)->current((string) auth()->id())?->id;
    }

    public function isGuardian(): bool
    {
        return $this->learnerId() !== null && $this->learnerId() !== (string) auth()->id();
    }

    /** @return array{enabled: bool, daily_minutes: ?int, night_lock: bool, academy: ?int, choices: list<int>}|null */
    #[Computed]
    public function limits(): ?array
    {
        if (! $this->isGuardian()) {
            return null;
        }
        $limit = GameLimit::query()->find($this->learnerId());

        return ['enabled' => $limit?->enabled ?? true, 'daily_minutes' => $limit?->daily_minutes, 'night_lock' => (bool) ($limit?->night_lock ?? false),
            'academy' => GameSettings::current()->daily_minutes, 'choices' => SaveGameLimit::MINUTES];
    }

    /** What a guardian sees of the child on screen: today's time, the week's practice, a creature hatched today. */
    #[Computed]
    public function glance(): ?array
    {
        return $this->isGuardian() ? app(GuardianGlance::class)->for((string) $this->learnerId()) : null;
    }

    public function setMinutes(?int $minutes): void
    {
        $this->saveLimit(minutes: $minutes);
    }

    public function setNight(bool $on): void
    {
        $this->saveLimit(night: $on);
    }

    public function setEnabled(bool $on): void
    {
        $this->saveLimit(enabled: $on);
    }

    /** A guardian's choice for the child on screen; what is not given stays as it was. */
    private function saveLimit(?bool $enabled = null, int|false|null $minutes = false, ?bool $night = null): void
    {
        abort_unless($this->isGuardian(), 403);
        $now = $this->limits;
        app(SaveGameLimit::class)->handle((string) $this->learnerId(), (string) auth()->id(), $enabled ?? $now['enabled'],
            $minutes === false ? $now['daily_minutes'] : ($minutes ?: null), $night);
        Notification::make()->success()->title(__('games::games.guardian.saved'))->send();
        unset($this->limits, $this->glance);
        $this->dispatch('journey-changed');
    }

    /** A guardian cheers the child for the creature they hatched (Learning's cheers: a few a day). */
    public function cheer(): void
    {
        abort_unless($this->isGuardian(), 403);
        try {
            app(LearningApi::class)->cheer((string) auth()->id(), (string) $this->learnerId(), 'heart');
            Notification::make()->success()->title(__('games::games.guardian.cheered'))->send();
        } catch (ValidationException $e) {
            Notification::make()->warning()->title(collect($e->errors())->flatten()->first())->send();
        }
    }

    public function render(): View
    {
        return view('games::filament.family.guardian');
    }
}
