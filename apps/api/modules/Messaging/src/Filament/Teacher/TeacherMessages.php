<?php

namespace Modules\Messaging\Filament\Teacher;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Messaging\Filament\Concerns\HoldsConversations;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Queries\MyConversations;

/** "Messages" for a teacher: each student they teach, with the student's guardians in every conversation. */
final class TeacherMessages extends Page
{
    use HoldsConversations;

    protected string $view = 'messaging::filament.chat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'messages';

    public static function canAccess(): bool
    {
        return auth()->check() && app(MessagingRules::class)->isTeacher((string) auth()->id());
    }

    public static function getNavigationLabel(): string
    {
        return __('messaging::screens.nav.teacher');
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = app(MyConversations::class)->unread((string) auth()->id());

        return $unread > 0 ? (string) $unread : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('messaging::screens.title.teacher');
    }

    protected function side(): string
    {
        return 'teacher';
    }

    protected function learnerId(): ?string
    {
        return null;
    }
}
