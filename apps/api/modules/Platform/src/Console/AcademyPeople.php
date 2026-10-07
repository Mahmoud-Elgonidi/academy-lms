<?php

namespace Modules\Platform\Console;

use Illuminate\Support\Facades\DB;

/**
 * Who runs an academy, for the platform team to reach: its owner and
 * admins, read across academies on the platform connection.
 */
final class AcademyPeople
{
    /** @return list<array{id: string, name: string, email: ?string, phone: ?string, roles: list<string>, active: bool}> */
    public function managers(string $academyId): array
    {
        $rows = DB::connection('pgsql_platform')->table('users as u')
            ->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
            ->where('u.academy_id', $academyId)
            ->whereIn('r.role', ['owner', 'admin'])
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email', 'u.phone_e164', 'u.status', 'r.role']);

        $people = [];
        foreach ($rows as $row) {
            $people[$row->id] ??= ['id' => $row->id, 'name' => $row->name, 'email' => $row->email, 'phone' => $row->phone_e164, 'roles' => [], 'active' => $row->status === 'active'];
            $people[$row->id]['roles'][] = $row->role;
        }
        // The owner first, then the admins.
        usort($people, fn (array $a, array $b) => [! in_array('owner', $a['roles'], true), $a['name']] <=> [! in_array('owner', $b['roles'], true), $b['name']]);

        return array_values($people);
    }
}
