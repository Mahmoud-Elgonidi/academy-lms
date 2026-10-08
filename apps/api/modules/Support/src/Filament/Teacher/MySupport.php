<?php

namespace Modules\Support\Filament\Teacher;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\WithFileUploads;
use Modules\Support\Filament\Concerns\RequesterTickets;
use Modules\Support\Queries\MyTickets;
use Modules\Support\SupportRules;

/** "Support" for a teacher: writing to the academy about a technical problem, their pay or anything else, and following the answers. */
final class MySupport extends Page
{
    use RequesterTickets;
    use WithFileUploads;

    protected string $view = 'support::filament.mine';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'support';

    public static function canAccess(): bool
    {
        return auth()->check() && app(SupportRules::class)->isTeacher((string) auth()->id());
    }

    public static function getNavigationLabel(): string
    {
        return __('support::tickets.mine.teacher_nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = app(MyTickets::class)->waitingOn((string) auth()->id());

        return $waiting > 0 ? (string) $waiting : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('support::tickets.mine.teacher_title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('support::tickets.mine.teacher_subtitle');
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
