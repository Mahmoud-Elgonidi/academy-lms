<?php

namespace Modules\Payroll\Queries;

use Illuminate\Support\Facades\DB;

/** The academy's teachers, for choosing who a card or a bonus is for. */
final class PayrollTeachers
{
    /** @return array<string, string> id => name */
    public function options(): array
    {
        return DB::table('users as u')
            ->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
            ->where('r.role', 'teacher')->where('u.status', 'active')->orderBy('u.name')
            ->pluck('u.name', 'u.id')->all();
    }

    public function isTeacher(string $userId): bool
    {
        return DB::table('user_roles')->where('user_id', $userId)->where('role', 'teacher')->exists();
    }

    public function name(string $userId): ?string
    {
        return DB::table('users')->where('id', $userId)->value('name');
    }
}
