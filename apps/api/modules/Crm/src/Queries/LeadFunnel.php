<?php

namespace Modules\Crm\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The last days at a glance: new leads, trials booked and attended, leads
 * won, and how many attended trials became subscriptions.
 */
final class LeadFunnel
{
    /** @return array{leads: int, booked: int, attended: int, won: int, rate: ?int} */
    public function since(CarbonImmutable $from, ?string $ownerId = null): array
    {
        $leads = DB::table('leads')->where('created_at', '>=', $from)->when($ownerId, fn ($q, $id) => $q->where('owner_id', $id));
        $trials = DB::table('lead_trials as t')->join('leads as l', fn ($j) => $j->on('l.academy_id', '=', 't.academy_id')->on('l.id', '=', 't.lead_id'))
            ->where('t.created_at', '>=', $from)->when($ownerId, fn ($q, $id) => $q->where('l.owner_id', $id));
        $attended = (clone $trials)->where('t.status', 'attended')->count();
        $won = DB::table('leads')->where('stage', 'won')->where('stage_changed_at', '>=', $from)->when($ownerId, fn ($q, $id) => $q->where('owner_id', $id))->count();
        $wonAfterTrial = DB::table('leads as l')->where('l.stage', 'won')->where('l.stage_changed_at', '>=', $from)
            ->when($ownerId, fn ($q, $id) => $q->where('l.owner_id', $id))
            ->whereExists(fn ($q) => $q->from('lead_trials as t')->whereColumn('t.lead_id', 'l.id')->whereColumn('t.academy_id', 'l.academy_id')->where('t.status', 'attended'))
            ->count();

        return [
            'leads' => $leads->count(),
            'booked' => (clone $trials)->count(),
            'attended' => $attended,
            'won' => $won,
            'rate' => $attended > 0 ? (int) round(100 * min(1, $wonAfterTrial / $attended)) : null,
        ];
    }
}
