<?php

namespace Modules\Learning\Filament\Family;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;

/** Where the learner is on each course's roadmap: done, here, to review, to come; and when they should finish. */
final class FamilyProgress extends Page
{
    protected string $view = 'learning::filament.family.progress';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'progress';

    public static function getNavigationLabel(): string
    {
        return __('learning::family.progress.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::family.progress.title');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function courses(): array
    {
        $learner = FamilyLearning::learner();

        return $learner ? FamilyLearning::courses($learner->id) : [];
    }
}
