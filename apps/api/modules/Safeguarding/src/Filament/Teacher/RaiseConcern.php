<?php

namespace Modules\Safeguarding\Filament\Teacher;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Safeguarding\ConcernRules;
use Modules\Safeguarding\Filament\Concerns\RaisesConcerns;

/**
 * "Raise a concern" in /teach: a teacher tells the safeguarding leads what
 * worries them about a student they teach (from a lesson of the last month,
 * or a student of theirs), and sees where what they raised stands.
 */
final class RaiseConcern extends Page
{
    use RaisesConcerns;

    protected string $view = 'safeguarding::filament.tell';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 88;

    protected static ?string $slug = 'raise-concern';

    public static function canAccess(): bool
    {
        return auth()->check() && app(ConcernRules::class)->isTeacher((string) auth()->id());
    }

    public static function getNavigationLabel(): string
    {
        return __('safeguarding::screens.teacher.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('safeguarding::screens.teacher.title');
    }

    public function side(): string
    {
        return 'teacher';
    }

    public function look(): string
    {
        return 'teacher';
    }

    public function learnerName(): ?string
    {
        return null;
    }

    protected function learnerId(): ?string
    {
        return null;
    }
}
