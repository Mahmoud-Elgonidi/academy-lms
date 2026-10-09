<?php

namespace Modules\SharedKernel\Authorization;

use Modules\SharedKernel\Plans\Feature;

/**
 * Back-office abilities. Modules check them with Gate::allows(Ability::X->value)
 * and never look at roles; Identity maps roles to abilities (AbilityMatrix).
 * An ability of an area the academy's plan may leave out (feature()) is
 * granted only when the plan includes it, so the area's screens and routes
 * close together.
 */
enum Ability: string
{
    case ManageBranding = 'academy.manage-branding';
    case ViewLiveBoard = 'live-board.view';
    case ManageSessions = 'sessions.manage';
    case ReviewShifts = 'shifts.review';
    case ProposeOwnShifts = 'shifts.propose-own';
    case FindTeachers = 'teachers.find';
    case ViewEnrollments = 'enrollments.view';
    case ViewBilling = 'billing.view';
    case RecordPayments = 'billing.record-payments';
    case ManageBilling = 'billing.manage';
    case ManagePolicies = 'policies.manage';
    case ManageCurriculum = 'curriculum.manage';
    case WriteReports = 'reports.write';
    case ReviewReports = 'reports.review';
    case ViewMessageLog = 'messages.view-log';
    case ManageTemplates = 'messages.manage-templates';
    case ManageClassroom = 'classroom.manage';
    case ViewRecordings = 'recordings.view';
    case ManageRecordings = 'recordings.manage';
    case ManageTimeOff = 'time-off.manage';
    case RequestOwnTimeOff = 'time-off.request-own';
    case ManageHolidays = 'holidays.manage';
    case ManageLeads = 'leads.manage';
    case BookTrials = 'trials.book';
    case AssessTrials = 'trials.assess';
    case ReviewPerformance = 'performance.review';
    case ViewOwnPerformance = 'performance.view-own';
    case ManagePayroll = 'payroll.manage';
    case ViewOwnEarnings = 'payroll.view-own';
    case ImportData = 'data.import';
    case ViewAuditLog = 'audit.view';
    case ManageConsents = 'consents.manage';
    case ViewPlan = 'academy.view-plan';
    case HandleTickets = 'tickets.handle';
    case DecideComplaints = 'complaints.decide';
    case ManageSupport = 'support.manage';
    case ManageCatalog = 'catalog.manage';
    case ManageTeachers = 'teachers.manage';
    case ManageStudents = 'students.manage';
    case HandleDataRequests = 'privacy.handle';

    /** The plan area this ability belongs to, if a plan may leave it out. */
    public function feature(): ?Feature
    {
        return match ($this) {
            self::ManageLeads, self::BookTrials, self::AssessTrials => Feature::Crm,
            self::ManagePayroll, self::ViewOwnEarnings => Feature::Payroll,
            self::ImportData => Feature::Import,
            default => null,
        };
    }
}
