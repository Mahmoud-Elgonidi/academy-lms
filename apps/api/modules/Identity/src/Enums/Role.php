<?php

namespace Modules\Identity\Enums;

/** Fixed system roles (MVP). Per-academy custom roles come in P3. */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Scheduler = 'scheduler';
    case Accountant = 'accountant';
    case Sales = 'sales';
    case Teacher = 'teacher';
    case Student = 'student';
    case Guardian = 'guardian';

    /** Roles that work in the academy back office (Filament). */
    public function isStaff(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Supervisor, self::Scheduler, self::Accountant, self::Sales], true);
    }
}
