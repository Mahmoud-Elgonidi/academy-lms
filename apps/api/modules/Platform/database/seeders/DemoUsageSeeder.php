<?php

namespace Modules\Platform\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Metering\Meters;
use Modules\SharedKernel\Plans\Meter;

/**
 * The demo academy's usage ledger (docs/architecture/02, section 11): last
 * month's use day by day (for the comparison), this month's allowance, a
 * pack of AI credits and one of video minutes bought earlier this month,
 * and the days of this month so far used by its teachers' lessons and the
 * AI helpers, so «الاستهلاك والرصيد» reads like an academy at work. Once: a
 * demo whose ledger has the demo's lines stays as it is; nothing without the
 * demo academy.
 */
final class DemoUsageSeeder extends Seeder
{
    /** What the AI helpers used on a working day, by capability. */
    private const AI_DAY = ['practice_items' => 4, 'report_draft' => 9, 'digest_wording' => 2, 'recitation_check' => 6, 'curriculum_outline' => 5];

    public function run(TenantDirectory $academies, Meters $meters): void
    {
        $demo = $academies->bySlug('demo');
        $db = DB::connection('pgsql_platform');
        if ($demo === null || $db->table('usage_ledger')->where('academy_id', $demo->id)->where('ref_type', 'demo')->exists()) {
            return;
        }
        $id = $demo->id;
        $tz = $meters->zone($id);
        $now = CarbonImmutable::now();
        $month = $meters->month($id, $now);
        $last = $meters->month($id, $month->from->subDay());
        $teachers = $db->table('user_roles as r')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'r.academy_id')->on('u.id', '=', 'r.user_id'))
            ->where('r.academy_id', $id)->where('r.role', 'teacher')->orderBy('u.created_at')->limit(5)->pluck('u.id')->all();
        $owner = $db->table('user_roles')->where('academy_id', $id)->where('role', 'owner')->value('user_id');
        $people = array_values(array_filter([...$teachers, $owner]));

        // Last month: its allowance, what each day used, and the rest written off at its end.
        foreach (Meter::cases() as $meter) {
            $limit = $meters->limitOf($id, $meter);
            if ($limit === null) {
                continue;
            }
            $lot = (string) Str::uuid7();
            $db->table('usage_ledger')->insert(['id' => $lot, 'academy_id' => $id, 'meter' => $meter->value, 'kind' => Ledger::ALLOWANCE, 'amount' => $limit, 'lot_id' => $lot,
                'expires_at' => $last->to, 'period' => $last->key($tz), 'ref_type' => 'demo', 'ref_id' => 'last-month', 'occurred_at' => $last->from, 'created_at' => $last->from]);
            $left = $limit;
            foreach ($this->days($last->from->setTimezone($tz), $last->to->setTimezone($tz)) as $i => $day) {
                foreach ($this->dayUse($meter, $i, $meter === Meter::AiCredits ? 2.5 : 0.85) as $subject => $used) {
                    $take = min($left, $used);
                    if ($take <= 0) {
                        continue;
                    }
                    $at = $day->setTime(17, 0)->addMinutes(($i * 7) % 180)->utc();
                    $db->table('usage_ledger')->insert(['id' => (string) Str::uuid7(), 'academy_id' => $id, 'meter' => $meter->value, 'kind' => Ledger::CONSUME, 'amount' => -$take,
                        'lot_id' => $lot, 'subject' => $subject, 'user_id' => $people === [] ? null : $people[$i % count($people)], 'ref_type' => 'demo', 'occurred_at' => $at, 'created_at' => $at]);
                    $left -= $take;
                }
            }
            if ($left > 0) {
                $db->table('usage_ledger')->insert(['id' => (string) Str::uuid7(), 'academy_id' => $id, 'meter' => $meter->value, 'kind' => Ledger::EXPIRE, 'amount' => -$left,
                    'lot_id' => $lot, 'ref_type' => 'demo', 'occurred_at' => $last->to, 'created_at' => $last->to]);
            }
        }

        // This month: the allowance, the packs bought on the third, and each day so far.
        foreach (Meter::cases() as $meter) {
            if ($meters->limitOf($id, $meter) === null) {
                continue;
            }
            $meters->ensureAllowance($id, $meter, $now);
            $meters->addLot($id, $meter, Ledger::PURCHASE, $meter === Meter::AiCredits ? 720 : 600, $now->addMonths(Ledger::LOT_MONTHS), ['ref_type' => 'demo', 'ref_id' => 'pack-'.$meter->value, 'occurred_at' => $month->from->addDays(2)->addHours(9)]);
            foreach ($this->days($month->from->setTimezone($tz), $now->setTimezone($tz)->startOfDay()) as $i => $day) {
                foreach ($this->dayUse($meter, $i, $meter === Meter::AiCredits ? 15.0 : 1.6) as $subject => $used) {
                    $meters->consume($id, $meter, $used, [
                        'subject' => $subject, 'user_id' => $people === [] ? null : $people[($i + strlen($subject)) % count($people)],
                        'ref_type' => 'demo', 'occurred_at' => $day->setTime(16, 0)->addMinutes(($i * 11) % 240)->utc(),
                    ]);
                }
            }
        }
    }

    /** @return list<CarbonImmutable> the days in [from, to) on the academy's clock */
    private function days(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        for ($d = $from->startOfDay(); $d->lessThan($to); $d = $d->addDay()) {
            $out[] = $d;
        }

        return $out;
    }

    /** @return array<string, int> what a day used, by what used it: fewer on Fridays, a little more each week */
    private function dayUse(Meter $meter, int $day, float $scale): array
    {
        $quiet = $day % 7 === 5 ? 0.3 : 1.0;
        $wave = 1 + (($day * 37) % 10) / 20;
        if ($meter === Meter::VideoMinutes) {
            return ['lesson' => (int) round(750 * $scale * $quiet * $wave)];
        }
        $out = [];
        foreach (self::AI_DAY as $subject => $n) {
            $used = (int) round($n * $scale * $quiet * $wave * (($day + strlen($subject)) % 3 === 0 ? 0.4 : 1));
            if ($used > 0 && ($subject !== 'curriculum_outline' || $day % 6 === 1)) {
                $out[$subject] = $used;
            }
        }

        return $out;
    }
}
