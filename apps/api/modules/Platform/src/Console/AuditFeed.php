<?php

namespace Modules\Platform\Console;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Audit\AuditEntries;

/**
 * The audit log across academies for the console, newest first: by academy
 * (or the platform's own entries), by kind of action and by who did it.
 */
final class AuditFeed
{
    public const PLATFORM_ONLY = 'platform';

    /**
     * @return array{rows: list<object>, more: bool, names: array<string, string>}
     */
    public function entries(?string $academy = null, ?string $group = null, ?string $who = null, int $limit = 50): array
    {
        $db = DB::connection('pgsql_platform');
        $query = $db->table('audit_logs as l')
            ->leftJoin('academies as a', 'a.id', '=', 'l.academy_id')
            ->orderByDesc('l.created_at')->orderByDesc('l.id')
            ->limit($limit + 1)
            ->select(['l.*', 'a.name_ar as academy_ar', 'a.name_en as academy_en', 'a.slug as academy_slug']);

        if ($academy === self::PLATFORM_ONLY) {
            $query->whereNull('l.academy_id');
        } elseif ($academy !== null && $academy !== '') {
            $query->where('l.academy_id', $academy);
        }
        if ($group !== null && isset(AuditEntries::GROUPS[$group])) {
            $query->where(function (Builder $q) use ($group) {
                foreach (AuditEntries::GROUPS[$group] as $prefix) {
                    $q->orWhere('l.action', 'like', $prefix.'%');
                }
            });
        }
        if (in_array($who, ['platform', 'user', 'system'], true)) {
            $query->where('l.actor_type', $who);
        }

        $rows = $query->get()->all();
        $more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        $ids = array_values(array_unique(array_filter(array_map(fn (object $r) => $r->on_behalf_of, $rows))));
        $names = $ids === [] ? [] : $db->table('users')->whereIn('id', $ids)->pluck('name', 'id')->all();

        return ['rows' => $rows, 'more' => $more, 'names' => $names];
    }
}
