<?php

namespace Modules\Crm\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Contracts\TrialConversionRates;

/**
 * Trial conversion per teacher (docs/architecture/02, KPIs: "تحويل الحصص
 * التجريبية" = trials that subscribed ÷ trials attended), over the last six
 * months. A subscription counts for the teacher of the lead's last attended
 * trial. Fewer than MIN_TRIALS attended trials say nothing yet.
 */
final class TrialConversion implements TrialConversionRates
{
    public const MIN_TRIALS = 3;

    public const MONTHS = 6;

    public function forTeachers(array $teacherIds): array
    {
        return collect($this->counts($teacherIds))
            ->filter(fn (array $c) => $c['attended'] >= self::MIN_TRIALS)
            ->map(fn (array $c) => $c['won'] / $c['attended'])
            ->all();
    }

    /**
     * @param  list<string>|null  $teacherIds  null: every teacher with trials
     * @return array<string, array{attended: int, won: int}>
     */
    public function counts(?array $teacherIds = null): array
    {
        if ($teacherIds === []) {
            return [];
        }

        return DB::table('lead_trials as t')
            ->join('leads as l', fn ($j) => $j->on('l.academy_id', '=', 't.academy_id')->on('l.id', '=', 't.lead_id'))
            ->where('t.status', 'attended')
            ->where('t.starts_at', '>', CarbonImmutable::now()->subMonths(self::MONTHS))
            ->when($teacherIds !== null, fn ($q) => $q->whereIn('t.teacher_id', $teacherIds))
            ->groupBy('t.teacher_id')
            ->selectRaw("t.teacher_id, count(*) as attended, count(*) filter (where l.stage = 'won' and t.id = (
                select t2.id from lead_trials t2 where t2.academy_id = t.academy_id and t2.lead_id = t.lead_id and t2.status = 'attended' order by t2.starts_at desc limit 1
            )) as won")
            ->get()
            ->mapWithKeys(fn ($r) => [$r->teacher_id => ['attended' => (int) $r->attended, 'won' => (int) $r->won]])
            ->all();
    }
}
