<?php

namespace Modules\Messaging\Filament\Family;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Messaging\Filament\Concerns\HoldsConversations;
use Modules\Messaging\Queries\MyConversations;
use Modules\People\Contracts\FamilyView;

/**
 * Messages in the family area (/my), in every look: the learner on screen
 * with each of their teachers. A guardian is in every conversation of their
 * child; a child writes too, in a child's words.
 */
final class FamilyMessages extends Page
{
    use HoldsConversations;

    protected string $view = 'messaging::filament.chat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'messages';

    public static function canAccess(): bool
    {
        return auth()->check() && app(FamilyView::class)->current((string) auth()->id()) !== null;
    }

    public static function getNavigationLabel(): string
    {
        return __('messaging::screens.nav.'.app(FamilyView::class)->stage((string) auth()->id())->value);
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = app(MyConversations::class)->unread((string) auth()->id());

        return $unread > 0 ? (string) $unread : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('messaging::screens.title.'.$this->look());
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
