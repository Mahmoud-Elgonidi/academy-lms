<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The usage ledger (docs/architecture/02, section 11, "العدّاد والرصيد"):
 * one append-only list of entries per academy and meter (video minutes on
 * our own video, the AI helpers' weighted credits). Each month the plan's
 * monthly limit comes in as an allowance that ends with the month on the
 * academy's clock; extra packs come in as purchase lots that last 12 months;
 * the platform team may grant a bonus, refund or adjust (console, with a
 * reason). Uses draw from the allowance first, then from the lots ending
 * soonest; what nothing covers is overage the next lot covers first. A
 * request under way holds its estimate (reserve) until it is settled or
 * released. The balance is always worked out from the entries.
 *
 * Also: the AI credit rates the console sets per capability (ai_credit_rates;
 * none: the capability's own), each academy's metering choices
 * (academy_metering: the console's hard limit on video, the academy's 50%
 * alert), and the alerts already raised (usage_alerts, once each).
 *
 * What came before moves in once, for the month the migration runs in:
 * - a plan's (and an academy's own) AI uses become credits at the average
 *   weight of the five helpers' default rates: practice 1, weekly-note
 *   wording 1, outline 5, recitation check 2 (a one-minute recording) and
 *   report draft 3 (2 and a one-minute voice note): 12 / 5 = 2.4, so the
 *   starter plan's 100 uses become 240 credits, growth's 300 become 720 and
 *   pro's 1,000 become 2,400; the AI packs (100/300/1,000 uses) likewise
 *   become 240/720/2,400 credits, and so do the AI packs waiting for the
 *   platform team;
 * - each academy on a limited plan gets this month's allowances, and its
 *   active extra packs of this month become lots that still end with this
 *   month, as they were sold;
 * - this month's AI requests so far are taken from them at the new rates
 *   (the video minutes of this month's finished rooms are counted by the
 *   classroom's own sweep, at the actual open time).
 */
return new class extends Migration
{
    /** The average weight of the five helpers' default rates (see above). */
    private const AI_WEIGHT = 2.4;

    /** The default rates the history is charged at: base, per audio minute. */
    private const RATES = [
        'practice_items' => [1, 0], 'digest_wording' => [1, 0], 'curriculum_outline' => [5, 0],
        'recitation_check' => [0, 2], 'report_draft' => [2, 1],
    ];

    public function up(): void
    {
        Schema::create('usage_ledger', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('meter', 20);                         // SharedKernel\Plans\Meter
            $table->string('kind', 10);                          // Domain\Metering\Ledger::KINDS
            $table->integer('amount');                           // + adds (a lot, a release), − takes (a use, a hold, an expiry, a refund)
            $table->uuid('lot_id')->nullable();                  // a lot: its own id; a draw: the lot it took from (none: overage, or no limit)
            $table->timestampTz('expires_at')->nullable();       // a lot's end
            $table->date('period')->nullable();                  // an allowance's month (its first day, on the academy's clock)
            $table->boolean('overage')->default(false);          // a use no lot covered (owed), or its covering by a later lot
            $table->uuid('hold_id')->nullable();                 // a reserve, its release, and what was settled for it
            $table->string('subject', 40)->nullable();           // what used it: an AI capability's key, "lesson"
            $table->uuid('user_id')->nullable();                 // who, where known: the lesson's teacher, whoever asked the helper (users.id)
            $table->string('ref_type', 20)->nullable();          // room, ai, topup, subscription, overage, console, demo
            $table->string('ref_id', 64)->nullable();
            $table->text('reason')->nullable();                  // the platform team's, for a bonus, a refund or an adjustment
            $table->uuid('actor_id')->nullable();                // platform_admins.id, for the console's entries
            $table->timestampTz('occurred_at');                  // when it happened (a room's close), for the month it counts in
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['academy_id', 'meter', 'lot_id']);
            $table->index(['academy_id', 'meter', 'occurred_at']);
            $table->index(['academy_id', 'meter', 'ref_type', 'ref_id']);
        });
        DB::statement('alter table usage_ledger add constraint usage_ledger_lot foreign key (lot_id) references usage_ledger (id)');
        DB::statement("alter table usage_ledger add constraint usage_ledger_meter check (meter in ('video_minutes', 'ai_credits'))");
        DB::statement("alter table usage_ledger add constraint usage_ledger_kind check (kind in ('allowance', 'purchase', 'bonus', 'consume', 'reserve', 'release', 'expire', 'refund', 'adjust'))");
        // A lot is a row of its own (lot_id = id) with an end; every other row is not.
        DB::statement("alter table usage_ledger add constraint usage_ledger_lot_shape check ((kind in ('allowance', 'purchase', 'bonus') or (kind = 'adjust' and amount > 0)) = (coalesce(lot_id = id, false) and expires_at is not null))");
        DB::statement(<<<'SQL'
            alter table usage_ledger add constraint usage_ledger_sign check (case
                when kind in ('allowance', 'purchase', 'bonus', 'release') then amount > 0
                when kind in ('reserve', 'expire', 'refund') then amount < 0
                when kind = 'consume' then amount < 0 or (amount > 0 and overage and lot_id is null and ref_type = 'overage')
                else amount <> 0 end)
            SQL);
        DB::statement('alter table usage_ledger add constraint usage_ledger_overage check (not overage or lot_id is null)');
        DB::statement("alter table usage_ledger add constraint usage_ledger_hold check ((kind in ('reserve', 'release')) <= (hold_id is not null and lot_id is null))");
        DB::statement("alter table usage_ledger add constraint usage_ledger_allowance check (kind <> 'allowance' or period is not null)");
        DB::statement("alter table usage_ledger add constraint usage_ledger_draw_lot check (kind not in ('expire', 'refund') or lot_id is not null)");
        DB::statement("alter table usage_ledger add constraint usage_ledger_reason check (kind not in ('bonus', 'refund', 'adjust') or reason is not null or ref_type in ('demo', 'migration'))");
        DB::statement("create unique index usage_ledger_one_allowance on usage_ledger (academy_id, meter, period, ref_id) where kind = 'allowance'");
        DB::statement("create unique index usage_ledger_one_purchase on usage_ledger (academy_id, meter, ref_type, ref_id) where kind = 'purchase' and ref_id is not null");
        DB::statement("create unique index usage_ledger_one_reserve on usage_ledger (hold_id) where kind = 'reserve'");
        DB::statement("create unique index usage_ledger_one_release on usage_ledger (hold_id) where kind = 'release'");
        DB::statement("create unique index usage_ledger_one_expire on usage_ledger (lot_id) where kind = 'expire'");
        DB::statement('create index usage_ledger_holds on usage_ledger (academy_id, meter, hold_id) where hold_id is not null');

        // Kept as written: a correction is a new entry.
        DB::unprepared(<<<'SQL'
            create or replace function lms_usage_ledger_append_only() returns trigger language plpgsql as $$
            begin
                raise exception 'the usage ledger is append-only: a correction is a new entry';
            end $$;
            create trigger lms_usage_ledger_append_only before update or delete on usage_ledger
                for each row execute function lms_usage_ledger_append_only();
            SQL);

        Schema::create('ai_credit_rates', function (Blueprint $table) {
            $table->string('capability', 40)->primary();         // SharedKernel\Ai\Capability key
            $table->integer('base');                             // credits each request
            $table->integer('per_minute')->default(0);           // and each minute of audio heard
            $table->uuid('updated_by')->nullable();              // platform_admins.id
            $table->timestampsTz();
        });
        DB::statement('alter table ai_credit_rates add constraint ai_credit_rates_amounts check (base >= 0 and per_minute >= 0 and base <= 1000 and per_minute <= 1000)');

        Schema::create('academy_metering', function (Blueprint $table) {
            $table->tenant();
            $table->boolean('video_hard_limit')->default(false); // the console's: at zero, new rooms open on the teacher's outside link
            $table->boolean('half_alert')->default(true);        // the academy's: hear at 50% of the month's allowance too
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->primary('academy_id');
        });

        Schema::create('usage_alerts', function (Blueprint $table) {
            $table->tenant();
            $table->string('meter', 20);
            $table->string('key', 80);                           // "2026-11-01:80", "lot:{id}", "runout:2026-11-01"
            $table->timestampTz('raised_at')->useCurrent();
            $table->primary(['academy_id', 'meter', 'key']);
        });

        TenantTable::enableRls('usage_ledger');
        TenantTable::enableRls('academy_metering');
        TenantTable::enableRls('usage_alerts');
        DB::statement('alter table ai_credit_rates enable row level security');
        DB::statement('alter table ai_credit_rates force row level security');
        DB::statement('revoke all on ai_credit_rates from lms_app');
        DB::statement('revoke insert, update, delete on usage_ledger, academy_metering, usage_alerts from lms_app');
        DB::statement('revoke update, delete on usage_ledger from lms_platform');
        DB::statement('revoke update, delete on usage_alerts from lms_platform');

        $this->moveIn();
    }

    /** What came before, into credits and the ledger (see above). */
    private function moveIn(): void
    {
        $tables = ['saas_plans', 'saas_subscriptions', 'saas_topup_packs', 'usage_topups', 'academies', 'ai_usage', 'usage_ledger'];
        foreach ($tables as $t) {
            DB::statement("alter table {$t} no force row level security");
        }
        try {
            $w = self::AI_WEIGHT;
            DB::update('update saas_plans set ai_uses = round(ai_uses * ?::numeric), updated_at = now() where ai_uses is not null', [$w]);
            DB::update("update saas_topup_packs set amount = greatest(1, round(amount * ?::numeric)), updated_at = now() where \"limit\" = 'ai_uses'", [$w]);
            // A request is answered once and stays as it was asked: only its unit changes here.
            DB::statement('alter table usage_topups disable trigger lms_usage_topups_forward');
            DB::update("update usage_topups set amount = greatest(1, round(amount * ?::numeric)) where \"limit\" = 'ai_uses' and status in ('requested', 'active')", [$w]);
            DB::statement('alter table usage_topups enable trigger lms_usage_topups_forward');

            $now = CarbonImmutable::now();
            foreach (DB::table('saas_subscriptions')->whereNull('ended_at')->get() as $sub) {
                $sub = $this->creditsOwnLimit($sub, $now);
                $academy = DB::table('academies')->where('id', $sub->academy_id)->first(['id', 'default_timezone']);
                if ($academy !== null) {
                    $this->academyLedger($sub, (string) $academy->default_timezone, $now);
                }
            }
        } finally {
            foreach ($tables as $t) {
                DB::statement("alter table {$t} force row level security");
            }
        }
    }

    /** An academy's own AI uses, as credits: a plan row is never changed, so a new one takes its place. */
    private function creditsOwnLimit(object $sub, CarbonImmutable $now): object
    {
        $limits = json_decode((string) $sub->limits, true) ?: [];
        if (! array_key_exists('ai_uses', $limits) || $limits['ai_uses'] === null) {
            return $sub;
        }
        $limits['ai_uses'] = (int) round((int) $limits['ai_uses'] * self::AI_WEIGHT);
        DB::table('saas_subscriptions')->where('id', $sub->id)->update(['ended_at' => $now, 'updated_at' => $now]);
        $row = (array) $sub;
        $row['id'] = (string) Str::uuid7();
        $row['limits'] = json_encode($limits);
        $row['reason'] = 'AI uses became AI credits';
        $row['changed_by'] = null;
        $row['started_at'] = $now;
        $row['ended_at'] = null;
        $row['created_at'] = $row['updated_at'] = $now;
        DB::table('saas_subscriptions')->insert($row);

        return (object) $row;
    }

    private function academyLedger(object $sub, string $tz, CarbonImmutable $now): void
    {
        $monthStart = $now->setTimezone($tz)->startOfMonth();
        $key = $monthStart->toDateString();
        $from = $monthStart->utc();
        $to = $monthStart->addMonth()->utc();
        $plan = DB::table('saas_plans')->where('id', $sub->plan_id)->first(['video_minutes', 'ai_uses']);
        $own = json_decode((string) $sub->limits, true) ?: [];
        $limit = fn (string $name) => array_key_exists($name, $own) ? ($own[$name] === null ? null : (int) $own[$name]) : ($plan?->{$name} === null ? null : (int) $plan->{$name});

        foreach (['video_minutes' => 'video_minutes', 'ai_credits' => 'ai_uses'] as $meter => $limitName) {
            $max = $limit($limitName);
            $lots = [];
            if ($max !== null && $max > 0) {
                $lots[] = $this->lot($sub->academy_id, $meter, 'allowance', $max, $to, $now, ['period' => $key, 'ref_type' => 'subscription', 'ref_id' => $sub->id]);
            }
            if ($max !== null) {
                foreach (DB::table('usage_topups')->where('academy_id', $sub->academy_id)->where('limit', $limitName)->where('status', 'active')->where('period', $key)->get() as $topUp) {
                    $lots[] = $this->lot($sub->academy_id, $meter, 'purchase', (int) $topUp->amount, $to, $now, ['ref_type' => 'topup', 'ref_id' => $topUp->id]);
                }
            }
            if ($meter === 'ai_credits') {
                $this->chargeMonth($sub->academy_id, $lots, $max !== null, $from, $to, $now);
            }
        }
    }

    /** @return array{id: string, left: int} */
    private function lot(string $academyId, string $meter, string $kind, int $amount, CarbonImmutable $expires, CarbonImmutable $now, array $extra): array
    {
        $id = (string) Str::uuid7();
        DB::table('usage_ledger')->insert($extra + [
            'id' => $id, 'academy_id' => $academyId, 'meter' => $meter, 'kind' => $kind, 'amount' => $amount, 'lot_id' => $id,
            'expires_at' => $expires, 'occurred_at' => $now, 'created_at' => $now,
        ]);

        return ['id' => $id, 'left' => $amount];
    }

    /**
     * This month's AI requests so far, at the default rates, from the allowance and then the packs.
     *
     * @param  list<array{id: string, left: int}>  $lots
     */
    private function chargeMonth(string $academyId, array $lots, bool $limited, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): void
    {
        $rows = DB::table('ai_usage')->where('academy_id', $academyId)->where('outcome', 'ok')->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->groupBy('feature')
            ->selectRaw('feature, count(*) filter (where audio_seconds = 0) as texts, count(*) filter (where audio_seconds > 0) as heard, coalesce(sum(ceil(audio_seconds / 60.0)) filter (where audio_seconds > 0), 0) as minutes')
            ->get();
        foreach ($rows as $row) {
            [$base, $perMinute] = self::RATES[$row->feature] ?? [1, 0];
            // A helper that hears then writes is charged once, for what it heard.
            $credits = $perMinute > 0
                ? (int) $row->heard * $base + (int) $row->minutes * $perMinute
                : ((int) $row->texts + (int) $row->heard) * $base;
            $left = $credits;
            foreach ($lots as $i => $lot) {
                if ($left === 0 || $lot['left'] === 0) {
                    continue;
                }
                $take = min($left, $lot['left']);
                $this->use($academyId, $row->feature, -$take, $lot['id'], false, $now);
                $lots[$i]['left'] -= $take;
                $left -= $take;
            }
            if ($left > 0) {
                $this->use($academyId, $row->feature, -$left, null, $limited, $now);
            }
        }
    }

    private function use(string $academyId, string $feature, int $amount, ?string $lotId, bool $overage, CarbonImmutable $now): void
    {
        DB::table('usage_ledger')->insert([
            'id' => (string) Str::uuid7(), 'academy_id' => $academyId, 'meter' => 'ai_credits', 'kind' => 'consume', 'amount' => $amount,
            'lot_id' => $lotId, 'overage' => $overage, 'subject' => mb_substr($feature, 0, 40), 'ref_type' => 'migration',
            'occurred_at' => $now, 'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_alerts');
        Schema::dropIfExists('academy_metering');
        Schema::dropIfExists('ai_credit_rates');
        Schema::dropIfExists('usage_ledger');
        DB::unprepared('drop function if exists lms_usage_ledger_append_only()');
        // The units go back: credits to uses at the same weight (an academy's own limit stays as it is now).
        foreach (['saas_plans', 'saas_topup_packs', 'usage_topups'] as $t) {
            DB::statement("alter table {$t} no force row level security");
        }
        DB::update('update saas_plans set ai_uses = round(ai_uses / ?::numeric) where ai_uses is not null', [self::AI_WEIGHT]);
        DB::update("update saas_topup_packs set amount = greatest(1, round(amount / ?::numeric)) where \"limit\" = 'ai_uses'", [self::AI_WEIGHT]);
        DB::statement('alter table usage_topups disable trigger lms_usage_topups_forward');
        DB::update("update usage_topups set amount = greatest(1, round(amount / ?::numeric)) where \"limit\" = 'ai_uses' and status in ('requested', 'active')", [self::AI_WEIGHT]);
        DB::statement('alter table usage_topups enable trigger lms_usage_topups_forward');
        foreach (['saas_plans', 'saas_topup_packs', 'usage_topups'] as $t) {
            DB::statement("alter table {$t} force row level security");
        }
    }
};
