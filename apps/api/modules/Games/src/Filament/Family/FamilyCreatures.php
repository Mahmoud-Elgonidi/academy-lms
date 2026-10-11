<?php

namespace Modules\Games\Filament\Family;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Games\Actions\ChooseBuddy;
use Modules\Games\Actions\NameCreature;
use Modules\Games\Queries\GamesWorld;
use Modules\People\Contracts\FamilyView;

/**
 * The learner's album of creatures in the family area (the approved design,
 * Album; docs/architecture/02, section 18): every creature of the world,
 * the hatched ones in colour with their voice and story, the rest as a
 * shadow with how they hatch. The learner picks the buddy that keeps them
 * company and names their creatures; a guardian only looks. Reached from
 * the islands (not the menu).
 */
final class FamilyCreatures extends Page
{
    protected string $view = 'games::filament.family.creatures';

    protected static ?string $slug = 'games/creatures';

    protected static bool $shouldRegisterNavigation = false;

    /** A creature just hatched, opened on arrival. */
    #[Url(as: 'new')]
    public ?string $new = null;

    /** Only this island's creatures. */
    #[Url(as: 'island')]
    public ?string $island = null;

    public static function canAccess(): bool
    {
        return FamilyGames::canAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return __('games::games.album.title');
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function learnerId(): ?string
    {
        return app(FamilyView::class)->current((string) auth()->id())?->id;
    }

    public function isGuardian(): bool
    {
        return $this->learnerId() !== null && $this->learnerId() !== (string) auth()->id();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function creatures(): array
    {
        $learner = $this->learnerId();

        return $learner === null ? [] : app(GamesWorld::class)->album($learner);
    }

    public function chooseBuddy(string $key): void
    {
        $learner = $this->learnerId();
        abort_if($learner === null || $this->isGuardian(), 403);
        app(ChooseBuddy::class)->handle($learner, $key);
        unset($this->creatures);
    }

    public function rename(string $key, string $name): void
    {
        $learner = $this->learnerId();
        abort_if($learner === null || $this->isGuardian(), 403);
        app(NameCreature::class)->handle($learner, $key, $name);
        unset($this->creatures);
    }
}
