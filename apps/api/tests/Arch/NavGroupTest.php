<?php

use App\Filament\Academy\StartHere;
use Filament\Facades\Filament;
use Modules\Announcements\Filament\Pages\Announcements;
use Modules\Billing\Filament\Pages\PaymentSettings;
use Modules\Billing\Filament\Pages\Revenue;
use Modules\Billing\Filament\Pages\SavedCards;
use Modules\Billing\Filament\Resources\Coupons\CouponResource;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Filament\Resources\Plans\PlanResource;
use Modules\Catalog\Filament\Subjects\SubjectResource;
use Modules\Classroom\Filament\Pages\ClassroomSettingsPage;
use Modules\Classroom\Filament\Pages\RecordingsPage;
use Modules\Communication\Filament\Pages\MessageLog;
use Modules\Communication\Filament\Pages\NotificationTemplates;
use Modules\Crm\Filament\Pages\Leads;
use Modules\Curriculum\Filament\Programs\ProgramResource;
use Modules\Evaluation\Filament\Pages\TeachersPerformance;
use Modules\Games\Filament\Pages\GameSettingsPage;
use Modules\Identity\Filament\Pages\ActivityLog;
use Modules\Identity\Filament\Pages\SecuritySettings;
use Modules\Identity\Filament\Pages\Team;
use Modules\Import\Filament\Pages\ImportData;
use Modules\Learning\Filament\Pages\ReportsOversight;
use Modules\Learning\Filament\Pages\ReportTemplates;
use Modules\Learning\Filament\Pages\WeeklyNoteSettings;
use Modules\Messaging\Filament\Pages\Conversations;
use Modules\Payroll\Filament\Pages\Payroll;
use Modules\Payroll\Filament\Resources\RateCards\RateCardResource;
use Modules\People\Filament\Directory\Guardians\GuardianResource;
use Modules\People\Filament\Directory\Students\StudentResource;
use Modules\People\Filament\Directory\Teachers\TeacherResource;
use Modules\People\Filament\Pages\GuardianConsents;
use Modules\People\Filament\Pages\TeacherChecks;
use Modules\Platform\Filament\Pages\AcademyModulesPage;
use Modules\Platform\Filament\Pages\AcademyPlanPage;
use Modules\Platform\Filament\Pages\AiSettingsPage;
use Modules\Platform\Filament\Pages\BrandingSettings;
use Modules\Platform\Filament\Pages\UsagePage;
use Modules\Privacy\Filament\Pages\DataRequests;
use Modules\Quran\Filament\Pages\HifzOverview;
use Modules\Quran\Filament\Pages\QuranCertificates;
use Modules\Quran\Filament\Pages\QuranExams;
use Modules\Quran\Filament\Pages\QuranHomePage;
use Modules\Quran\Filament\Pages\QuranPrograms;
use Modules\Quran\Filament\Pages\QuranSettingsPage;
use Modules\Quran\Filament\Pages\QuranStudents;
use Modules\Safeguarding\Filament\Pages\Safeguarding;
use Modules\Scheduling\Filament\Pages\AcademyCalendar;
use Modules\Scheduling\Filament\Pages\DstChanges;
use Modules\Scheduling\Filament\Pages\FindTeacher;
use Modules\Scheduling\Filament\Pages\Groups;
use Modules\Scheduling\Filament\Pages\GroupSettings;
use Modules\Scheduling\Filament\Pages\Holidays;
use Modules\Scheduling\Filament\Pages\LessonDisputes;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Modules\Scheduling\Filament\Pages\PolicySettings;
use Modules\Scheduling\Filament\Pages\ShiftProposals;
use Modules\Scheduling\Filament\Pages\TimeOffRequests;
use Modules\Scheduling\Filament\Resources\Enrollments\EnrollmentResource;
use Modules\SharedKernel\Filament\NavGroup;
use Modules\Site\Filament\Pages\SiteRequests;
use Modules\Site\Filament\Pages\SiteSettings;
use Modules\Support\Filament\Pages\Tickets;
use Tests\TestCase;

uses(TestCase::class);

/*
 * The back office's menu (docs/architecture/02, "وحدات الأكاديمية"): every
 * screen of the academy panel that shows in the menu sits in one of
 * SharedKernel's NavGroup groups, except the three at the top (Start here
 * while setup is left, the live board, the calendar), and inside each group
 * the screens come in the order the product chose.
 */

/** The menu, group by group, as it reads. */
const ACADEMY_MENU = [
    null => [StartHere::class, LiveBoard::class, AcademyCalendar::class],
    'Lessons' => [FindTeacher::class, EnrollmentResource::class, Groups::class,
        TimeOffRequests::class, ShiftProposals::class, LessonDisputes::class, DstChanges::class],
    'People' => [TeacherResource::class, StudentResource::class, GuardianResource::class,
        GuardianConsents::class, TeacherChecks::class],
    'Teaching' => [SubjectResource::class, ProgramResource::class, ReportsOversight::class,
        ReportTemplates::class, TeachersPerformance::class, RecordingsPage::class, GameSettingsPage::class],
    'Quran' => [QuranHomePage::class, QuranStudents::class, HifzOverview::class, QuranPrograms::class,
        QuranExams::class, QuranCertificates::class, QuranSettingsPage::class],
    'Sales' => [Leads::class, SiteRequests::class],
    'Finance' => [Revenue::class, InvoiceResource::class, SavedCards::class, Payroll::class,
        PlanResource::class, CouponResource::class, RateCardResource::class],
    'Communication' => [Conversations::class, Announcements::class, Tickets::class,
        MessageLog::class, NotificationTemplates::class],
    'Protection' => [Safeguarding::class, DataRequests::class, ActivityLog::class],
    'Settings' => [AcademyModulesPage::class, Team::class, SecuritySettings::class, Holidays::class,
        PolicySettings::class, ClassroomSettingsPage::class, GroupSettings::class, PaymentSettings::class,
        SiteSettings::class, AiSettingsPage::class, WeeklyNoteSettings::class, BrandingSettings::class,
        ImportData::class, AcademyPlanPage::class, UsagePage::class],
];

/** Whether a screen can show in the menu at all (not one opened from another screen). */
function inMenu(string $class): bool
{
    $source = (string) file_get_contents((new ReflectionClass($class))->getFileName());

    return preg_match('/\$shouldRegisterNavigation\s*=\s*false/', $source) !== 1;
}

it('puts every academy screen in the menu into a NavGroup, or at the top', function () {
    $panel = Filament::getPanel('academy');
    $top = ACADEMY_MENU[null];
    $wrong = [];
    foreach ([...$panel->getPages(), ...$panel->getResources()] as $class) {
        if (! inMenu($class) || in_array($class, $top, true)) {
            continue;
        }
        if (! $class::getNavigationGroup() instanceof NavGroup) {
            $wrong[] = $class;
        }
    }

    expect($wrong)->toBe([], 'Return a SharedKernel\Filament\NavGroup from getNavigationGroup().');
});

it('reads the menu in the chosen order, group by group', function () {
    $panel = Filament::getPanel('academy');
    $listed = array_merge(...array_values(ACADEMY_MENU));
    $registered = array_values(array_filter([...$panel->getPages(), ...$panel->getResources()], 'inMenu'));
    expect(array_diff($registered, $listed))->toBe([], 'A new screen in the menu goes into ACADEMY_MENU, where it reads.');

    foreach (ACADEMY_MENU as $group => $classes) {
        if ($group === null || $group === '') {
            continue;
        }
        $case = constant(NavGroup::class.'::'.$group);
        $sorts = [];
        foreach ($classes as $class) {
            expect($class::getNavigationGroup())->toBe($case, $class);
            $sorts[] = $class::getNavigationSort();
        }
        $sorted = $sorts;
        sort($sorted);
        expect($sorts)->toBe($sorted, $group)->and(count(array_unique($sorts)))->toBe(count($sorts), $group);
    }
    // Groups come in NavGroup's order.
    expect(array_keys(NavGroup::forPanel()))->toBe(array_values(array_filter(array_keys(ACADEMY_MENU), fn ($k) => $k !== '' && $k !== null)));
});
