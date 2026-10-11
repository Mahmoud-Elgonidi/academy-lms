<?php

namespace Modules\Games\Filament\Family;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Modules\People\Contracts\FamilyView;

/**
 * The old games page (/my/games): the games now live on the learner's journey
 * (docs/architecture/02, section 18), so this page takes old links and
 * notifications there (Learning's journey page, by route name), keeping
 * ?homework= (that homework's round starts on arrival).
 */
final class FamilyGames extends Page
{
    protected string $view = 'games::filament.family.moved';

    protected static ?string $slug = 'games';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'homework')]
    public ?string $homework = null;

    public static function canAccess(): bool
    {
        return auth()->check() && app(FamilyView::class)->current((string) auth()->id()) !== null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('games::games.title.classic');
    }

    public function mount(): void
    {
        $this->redirect(route('filament.family.pages.journey', array_filter(['homework' => $this->homework])));
    }
}
