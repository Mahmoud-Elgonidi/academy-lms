<?php

namespace Modules\SharedKernel\Authorization;

/**
 * Back-office abilities. Modules check them with Gate::allows(Ability::X->value)
 * and never look at roles; Identity maps roles to abilities (AbilityMatrix).
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
    case ManageTimeOff = 'time-off.manage';
    case RequestOwnTimeOff = 'time-off.request-own';
    case ManageHolidays = 'holidays.manage';
}
