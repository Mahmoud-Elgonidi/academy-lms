<?php

namespace Modules\Identity\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;

/** The current academy's teachers who can work: what its plan's teacher limit counts. */
final class ActiveTeachers
{
    public function count(): int
    {
        return (int) DB::table('user_roles as r')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'r.academy_id')->on('u.id', '=', 'r.user_id'))
            ->where('r.role', Role::Teacher->value)
            ->where('u.status', UserStatus::Active->value)
            ->distinct()
            ->count('u.id');
    }
}
