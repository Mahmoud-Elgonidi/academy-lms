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
}
