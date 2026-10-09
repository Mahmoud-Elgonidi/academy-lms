<?php

namespace Modules\Privacy\Filament\Teacher;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Privacy\Filament\Concerns\OwnData;
use Modules\SharedKernel\Authorization\Ability;

/** "My data" in /teach: a teacher's copy of what the academy keeps about them, and erasure of their account. */
final class MyData extends Page
{
    use OwnData;

    protected string $view = 'privacy::filament.mine';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'my-data';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewOwnPerformance->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('privacy::screens.mine.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('privacy::screens.mine.title');
    }

    protected function side(): string
    {
        return 'teacher';
    }
}
