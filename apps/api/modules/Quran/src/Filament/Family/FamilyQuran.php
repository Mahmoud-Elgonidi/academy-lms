<?php

namespace Modules\Quran\Filament\Family;

use Modules\People\Contracts\DTOs\Learner;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;

/** The learner on the family's screen and the viewer's look, for the Quran's family pages (People's FamilyView). */
final class FamilyQuran
{
    public static function learner(): ?Learner
    {
        return app(FamilyView::class)->current((string) auth()->id());
    }

    /** The viewer's look: a student's own stage, the classic one for a guardian. */
    public static function stage(): LearnerStage
    {
        return app(FamilyView::class)->stage((string) auth()->id());
    }
}
