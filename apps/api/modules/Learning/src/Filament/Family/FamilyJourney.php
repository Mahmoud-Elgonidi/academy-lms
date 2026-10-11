<?php

namespace Modules\Learning\Filament\Family;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\People\Contracts\LearnerStage;

/**
 * The journey page (docs/architecture/02, section 18) of guardians and adults
 * ("الرحلة", where the progress was): the learner's journey in the classic
 * look (JourneyBoard). A student's journey is their home, so this page
 * takes them there (with ?homework=, which starts that homework's round);
 * old links (the games, the challenges, notifications) end up here too.
 */
final class FamilyJourney extends Page
{
    protected string $view = 'learning::filament.family.map.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'journey';

    public static function canAccess(): bool
    {
        return auth()->check() && FamilyLearning::learner() !== null;
    }

    /** Students find their journey on the home page. */
    public static function shouldRegisterNavigation(): bool
    {
        return FamilyLearning::stage() === LearnerStage::Classic;
    }

    public static function getNavigationLabel(): string
    {
        return __('learning::map.nav');
    }

    public function mount(): void
    {
        if (FamilyLearning::stage() !== LearnerStage::Classic) {
            $query = array_filter(['homework' => request()->query('homework')], fn ($v) => is_string($v) && $v !== '');
            $this->redirect(Filament::getUrl().($query === [] ? '' : '?'.http_build_query($query)));
        }
    }

    public function getTitle(): string|Htmlable
    {
        $learner = FamilyLearning::learner();
        if ($learner === null || $learner->isSelf) {
            return __('learning::map.title.self');
        }

        return __('learning::map.title.guardian', ['name' => explode(' ', trim($learner->name))[0]]);
    }

    /** The journey draws its own header. */
    public function getHeading(): string|Htmlable
    {
        return '';
    }
}
