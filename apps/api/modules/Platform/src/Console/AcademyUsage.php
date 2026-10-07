<?php

namespace Modules\Platform\Console;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * How much each academy uses the platform (docs/architecture/01: "لوحة
 * Super Admin: الأكاديميات، والاستخدام"), counted across academies on the
 * platform connection: people by role, live enrollments, lessons and taught
 * minutes in the last 30 days, and money received in that time.
 */
final class AcademyUsage
{
    /**
     * @param  list<string>  $academyIds
     * @return array<string, array{teachers: int, students: int, guardians: int, staff: int, enrollments: int, lessons: int, minutes: int, received: array<string, int>, last_lesson: ?string}>
     */
    public function forAcademies(array $academyIds, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $since = $now->subDays(30);
        $db = DB::connection('pgsql_platform');
        $usage = array_fill_keys($academyIds, ['teachers' => 0, 'students' => 0, 'guardians' => 0, 'staff' => 0, 'enrollments' => 0, 'lessons' => 0, 'minutes' => 0, 'received' => [], 'last_lesson' => null]);
        if ($academyIds === []) {
            return [];
        }

        // People by kind, each counted once (an owner who is also an admin is one of the staff).
        $kind = "case r.role when 'teacher' then 'teachers' when 'student' then 'students' when 'guardian' then 'guardians' else 'staff' end";
        $people = $db->table('user_roles as r')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'r.academy_id')->on('u.id', '=', 'r.user_id'))
            ->whereIn('r.academy_id', $academyIds)->where('u.status', 'active')
            ->groupBy('r.academy_id', DB::raw($kind))
            ->get(['r.academy_id', DB::raw("{$kind} as kind"), DB::raw('count(distinct u.id) as n')]);
        foreach ($people as $r) {
            $usage[$r->academy_id][$r->kind] = (int) $r->n;
        }

        foreach ($db->table('enrollments')->whereIn('academy_id', $academyIds)->whereIn('status', ['active', 'pending_payment'])->groupBy('academy_id')->get(['academy_id', DB::raw('count(*) as n')]) as $r) {
            $usage[$r->academy_id]['enrollments'] = (int) $r->n;
        }

        $lessons = $db->table('sessions')->whereIn('academy_id', $academyIds)->where('status', 'completed')->where('starts_at', '>=', $since)->where('starts_at', '<', $now)
            ->groupBy('academy_id')->get(['academy_id', DB::raw('count(*) as n'), DB::raw('coalesce(sum(extract(epoch from ends_at - starts_at) / 60), 0) as minutes')]);
        foreach ($lessons as $r) {
            $usage[$r->academy_id]['lessons'] = (int) $r->n;
            $usage[$r->academy_id]['minutes'] = (int) round((float) $r->minutes);
        }

        foreach ($db->table('sessions')->whereIn('academy_id', $academyIds)->where('status', 'completed')->groupBy('academy_id')->get(['academy_id', DB::raw('max(starts_at) as at')]) as $r) {
            $usage[$r->academy_id]['last_lesson'] = $r->at;
        }

        $paid = $db->table('payments')->whereIn('academy_id', $academyIds)->where('received_at', '>=', $since)
            ->groupBy('academy_id', 'currency')->get(['academy_id', 'currency', DB::raw('sum(amount_minor) as minor')]);
        foreach ($paid as $r) {
            $usage[$r->academy_id]['received'][$r->currency] = (int) $r->minor;
        }

        return $usage;
    }
}
