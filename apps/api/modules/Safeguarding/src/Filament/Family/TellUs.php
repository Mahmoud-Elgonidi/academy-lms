<?php

namespace Modules\Safeguarding\Filament\Family;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\People\Contracts\FamilyView;
use Modules\Safeguarding\ConcernRules;
use Modules\Safeguarding\Filament\Concerns\RaisesConcerns;

/**
 * "Tell us" in /my, in every look: a child presses a button (words are up
 * to them), an older student or a guardian says what happened. It is about
 * the learner on screen, and only the academy's safeguarding leads read it.
 */
final class TellUs extends Page
{
    use RaisesConcerns;

    protected string $view = 'safeguarding::filament.tell';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 88;

    protected static ?string $slug = 'tell-us';

    public static function canAccess(): bool
    {
        return auth()->check() && app(FamilyView::class)->current((string) auth()->id()) !== null;
    }

    public static function getNavigationLabel(): string
    {
        return __('safeguarding::screens.family.nav.'.app(FamilyView::class)->stage((string) auth()->id())->value);
    }

    public function getTitle(): string|Htmlable
    {
        return __('safeguarding::screens.family.title.'.$this->look());
    }

    public function side(): string
    {
        return 'family';
    }

    public function look(): string
    {
        return app(ConcernRules::class)->look((string) auth()->id());
    }

    /** Who it is about, when a guardian follows more than one child. */
    public function learnerName(): ?string
    {
        $learner = app(FamilyView::class)->current((string) auth()->id());

        return $learner !== null && ! $learner->isSelf ? $learner->name : null;
    }

    protected function learnerId(): ?string
    {
        return app(FamilyView::class)->current((string) auth()->id())?->id;
    }
}
