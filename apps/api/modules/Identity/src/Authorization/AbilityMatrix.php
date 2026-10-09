<?php

namespace Modules\Identity\Authorization;

use Illuminate\Support\Facades\Gate;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\AcademyPlan;

/** Which fixed roles hold which ability (docs/architecture/01-features.md, permission matrix). */
final class AbilityMatrix
{
    /** @return list<Role> */
    public static function rolesFor(Ability $ability): array
    {
        return match ($ability) {
            Ability::ManageBranding, Ability::ManagePolicies, Ability::ManageTemplates, Ability::ManageClassroom, Ability::ManageHolidays => [Role::Owner, Role::Admin],
            Ability::ViewLiveBoard, Ability::ManageSessions, Ability::ReviewShifts, Ability::ManageTimeOff => [Role::Owner, Role::Admin, Role::Supervisor, Role::Scheduler],
            Ability::FindTeachers, Ability::BookTrials => [Role::Owner, Role::Admin, Role::Supervisor, Role::Scheduler, Role::Sales],
            // Leads and trials (CRM): sales, and the scheduler who books for them; a teacher assesses their own trials.
            Ability::ManageLeads => [Role::Owner, Role::Admin, Role::Scheduler, Role::Sales],
            Ability::ProposeOwnShifts, Ability::WriteReports, Ability::RequestOwnTimeOff, Ability::AssessTrials, Ability::ViewOwnPerformance, Ability::ViewOwnEarnings => [Role::Teacher],
            Ability::ManageCurriculum, Ability::ReviewReports, Ability::ViewMessageLog, Ability::ReviewPerformance => [Role::Owner, Role::Admin, Role::Supervisor],
            Ability::ViewEnrollments => [Role::Owner, Role::Admin, Role::Supervisor, Role::Scheduler, Role::Accountant],
            Ability::ViewBilling, Ability::RecordPayments => [Role::Owner, Role::Admin, Role::Accountant],
            // Prices, tax, regions and voiding invoices: the owner and the accountant (Admin is "◐" for billing).
            Ability::ManageBilling => [Role::Owner, Role::Accountant],
            Ability::ManagePayroll => [Role::Owner, Role::Accountant],
            Ability::ImportData => [Role::Owner, Role::Admin],
            // The academy's own audit log, the platform team's sign-ins included.
            Ability::ViewAuditLog => [Role::Owner, Role::Admin],
            // Guardians' consent for children's accounts: who follows the families day to day.
            Ability::ManageConsents => [Role::Owner, Role::Admin, Role::Supervisor],
            // Lesson recordings: who reviews lessons watches them (every opening is logged); keeping one past its days or removing it early is the owner's and admins'.
            Ability::ViewRecordings => [Role::Owner, Role::Admin, Role::Supervisor],
            Ability::ManageRecordings => [Role::Owner, Role::Admin],
            // The academy's SaaS plan and how much of it is used.
            Ability::ViewPlan => [Role::Owner, Role::Admin],
            // Support tickets: everyone at the academy may answer one (each kind is told to whoever it concerns);
            // confirming a complaint against a teacher is for who oversees the teachers; the answer targets for the owner and admins.
            Ability::HandleTickets => [Role::Owner, Role::Admin, Role::Supervisor, Role::Scheduler, Role::Accountant, Role::Sales],
            Ability::DecideComplaints => [Role::Owner, Role::Admin, Role::Supervisor],
            Ability::ManageSupport => [Role::Owner, Role::Admin],
            // Subjects with their levels and categories: the academy's setup.
            Ability::ManageCatalog => [Role::Owner, Role::Admin],
            // Adding and editing teachers, their subjects and profile: who runs the teachers and the timetable.
            Ability::ManageTeachers => [Role::Owner, Role::Admin, Role::Scheduler],
            // Students and guardians: who enrols them and who follows the families.
            Ability::ManageStudents => [Role::Owner, Role::Admin, Role::Supervisor, Role::Scheduler],
            // People's copies of their data and erasing their accounts: the academy answers for them (the controller), so its owner and admins.
            Ability::HandleDataRequests => [Role::Owner, Role::Admin],
            // Concerns about a child's safety and the teachers' vetting: the academy's own leads, kept to the fewest.
            Ability::HandleSafeguarding => [Role::Owner, Role::Admin],
        };
    }

    public static function register(): void
    {
        foreach (Ability::cases() as $ability) {
            // An ability of an area the academy's plan leaves out is nobody's.
            Gate::define($ability->value, fn (User $user): bool => $user->isActive() && $user->hasRole(...self::rolesFor($ability))
                && ($ability->feature() === null || app(AcademyPlan::class)->has($ability->feature())));
        }
    }
}
