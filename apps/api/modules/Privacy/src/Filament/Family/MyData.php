<?php

namespace Modules\Privacy\Filament\Family;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Privacy\Filament\Concerns\OwnData;

/** "My data" in /my: adults only (the classic look); a younger student's data is their guardian's to ask about. */
final class MyData extends Page
{
    use OwnData;

    protected string $view = 'privacy::filament.mine';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?int $navigationSort = 92;

    protected static ?string $slug = 'my-data';

    public static function canAccess(): bool
    {
        return auth()->check() && app(FamilyView::class)->stage((string) auth()->id()) === LearnerStage::Classic;
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
        return 'family';
    }
}
