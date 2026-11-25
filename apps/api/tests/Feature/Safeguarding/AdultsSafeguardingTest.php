<?php

use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Safeguarding\Actions\ReportConcern;
use Modules\Safeguarding\Enums\ConcernCategory;
use Modules\Safeguarding\Filament\Pages\Safeguarding;
use Modules\Safeguarding\Queries\ConcernBoard;
use Modules\SharedKernel\Tenancy\TenantContext;

/*
 * Concerns in an academy that trains adults only (docs/architecture/02,
 * section 29): safety and conduct among adults, in words for adults, with no
 * pause from under-18s on the board. A families academy keeps its words.
 */

beforeEach(function () {
    $this->withoutDefer();
    app()->setLocale('ar');
    $this->families = $this->createAcademy(['slug' => 'noor']);
    $this->adults = $this->actAsTenant($this->createAcademy(['slug' => 'hm-trust', 'audience' => 'adults']));
});

/** A concern the office writes down about a teacher of the current academy, and the board's view of it. */
function concernAboutTeacher(): array
{
    $owner = User::factory()->withRole(Role::Owner)->create();
    $teacher = User::factory()->withRole(Role::Teacher)->create();
    DB::table('teacher_profiles')->insert(['academy_id' => app(TenantContext::class)->require()->id, 'user_id' => $teacher->id, 'created_at' => now(), 'updated_at' => now()]);
    $concern = app(ReportConcern::class)->handle($owner->id, 'staff', null, 'adult_behaviour', 'Said something rude in the session.', false, null, $teacher->id);

    return app(ConcernBoard::class)->details($concern->id);
}

it('reads as safety and conduct, with no pause from under-18s on the board', function () {
    expect(concernAboutTeacher()['clearance'])->toBeNull()
        ->and(Safeguarding::getNavigationLabel())->toBe('السلامة والسلوك')
        ->and(ConcernCategory::Wellbeing->label())->toBe('شخص ليس بخير أو ليس في أمان')
        ->and(__('safeguarding::concerns.thanks'))->not->toContain('الأطفال');
});

it('keeps the words and the pause from under-18s of a families academy', function () {
    $this->actAsTenant($this->families);
    expect(concernAboutTeacher()['clearance'])->not->toBeNull()
        ->and(Safeguarding::getNavigationLabel())->toBe('حماية الأطفال')
        ->and(ConcernCategory::Wellbeing->label())->toBe('طفل ليس بخير أو ليس في أمان');
});
