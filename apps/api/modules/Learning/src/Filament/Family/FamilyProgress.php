<?php

namespace Modules\Learning\Filament\Family;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Modules\People\Contracts\LearnerStage;

/** Where the learner is on each course's roadmap: done, here, to review, to come; and when they should finish. */
final class FamilyProgress extends Page
{
    protected string $view = 'learning::filament.family.progress';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'progress';

    /** Children find their map on the home page. */
    public static function shouldRegisterNavigation(): bool
    {
        return FamilyLearning::stage() !== LearnerStage::Kids;
    }

    public static function getNavigationLabel(): string
    {
        return __('learning::family.progress.nav.'.FamilyLearning::stage()->value);
    }

    public function getTitle(): string|Htmlable
    {
        return FamilyLearning::stage() === LearnerStage::Classic ? __('learning::family.progress.title') : self::getNavigationLabel();
    }

    /** The whole map in the student's look (every island, world or milestone open); null for the classic roadmap. */
    #[Computed]
    public function journey(): ?array
    {
        return FamilyLearning::stage() === LearnerStage::Classic ? null : JourneyWidget::journey();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function courses(): array
    {
        $learner = FamilyLearning::learner();

        return $learner ? FamilyLearning::courses($learner->id) : [];
    }
}
