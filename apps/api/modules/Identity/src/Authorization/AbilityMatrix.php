<?php

namespace Modules\Identity\Authorization;

use Illuminate\Support\Facades\Gate;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Authorization\Ability;

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
        };
    }

    public static function register(): void
    {
        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn (User $user): bool => $user->isActive() && $user->hasRole(...self::rolesFor($ability)));
        }
    }
}
