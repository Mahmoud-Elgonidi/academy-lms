<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The month's AI helper uses and the extra packs (docs/architecture/02,
 * "باقات الأكاديميات وحدودها"). A plan now says how many uses of the AI
 * helpers an academy has a month (saas_plans.ai_uses, null: no limit; at the
 * limit the helpers stop and the work goes on by hand). The platform sells
 * extra packs of video minutes and AI uses (saas_topup_packs, set in the
 * console); an academy asks for one (usage_topups: requested), the platform
 * team collects the money its own way and turns it on (active: added to that
 * calendar month on the academy's clock, gone with it), or turns it down
 * with a reason. An academy may also ask for a bigger plan
 * (plan_upgrade_requests), which the team answers with the plan change.
 *
 * Only the platform writes these, on its own connection. A request keeps who
 * asked by id only; its status only moves forward, once.
 */
return new class extends Migration
{
    private const PLAN_AI_USES = ['starter' => 100, 'growth' => 300, 'pro' => 1000];

    public function up(): void
    {
        Schema::table('saas_plans', fn (Blueprint $table) => $table->integer('ai_uses')->nullable());   // a calendar month, on the academy's clock
        DB::statement('alter table saas_plans add constraint saas_plans_ai_uses check (coalesce(ai_uses, 0) >= 0)');
        DB::statement('alter table saas_plans no force row level security');
        foreach (self::PLAN_AI_USES as $code => $uses) {
            DB::update('update saas_plans set ai_uses = ?, updated_at = now() where code = ?', [$uses, $code]);
        }
        DB::statement('alter table saas_plans force row level security');

        Schema::create('saas_topup_packs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('limit', 30);                         // SharedKernel\Plans\Limit, a monthly one
            $table->integer('amount');                           // in the limit's unit (minutes, uses)
            $table->string('name_ar', 80);
            $table->string('name_en', 80);
            $table->bigInteger('price_minor')->nullable();       // what the platform asks, shown to the academy
            $table->char('currency', 3)->nullable();
            $table->smallInteger('sort')->default(0);
            $table->timestampTz('archived_at')->nullable();      // no longer offered; requests made from it stay
            $table->timestampsTz();
        });
        DB::statement("alter table saas_topup_packs add constraint saas_topup_packs_limit check (\"limit\" in ('video_minutes', 'ai_uses'))");
        DB::statement('alter table saas_topup_packs add constraint saas_topup_packs_amount check (amount > 0)');
        DB::statement('alter table saas_topup_packs add constraint saas_topup_packs_price check ((price_minor is null) = (currency is null) and coalesce(price_minor, 0) >= 0)');

        Schema::create('usage_topups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('limit', 30);
            $table->integer('amount');
            $table->date('period');                              // the month it counts in (its first day, on the academy's clock), as usage_counters
            $table->string('status', 12)->default('requested');  // requested → active | declined | cancelled
            $table->foreignUuid('pack_id')->nullable()->constrained('saas_topup_packs')->restrictOnDelete();
            $table->uuid('requested_by')->nullable();            // users.id (the academy's owner or admin)
            $table->text('note')->nullable();                    // the academy's, for the platform team
            $table->uuid('decided_by')->nullable();              // platform_admins.id (null when the academy cancelled)
            $table->timestampTz('decided_at')->nullable();
            $table->text('reason')->nullable();                  // why it was turned down, which the academy reads
            $table->timestampsTz();
            $table->index(['academy_id', 'period', 'status']);
            $table->index(['status', 'created_at']);
        });
        DB::statement("alter table usage_topups add constraint usage_topups_limit check (\"limit\" in ('video_minutes', 'ai_uses'))");
        DB::statement('alter table usage_topups add constraint usage_topups_amount check (amount > 0)');
        DB::statement("alter table usage_topups add constraint usage_topups_status check (status in ('requested', 'active', 'declined', 'cancelled'))");
        DB::statement("alter table usage_topups add constraint usage_topups_reason check (status <> 'declined' or reason is not null)");
        DB::statement("create unique index usage_topups_one_waiting on usage_topups (academy_id, \"limit\") where status = 'requested'");

        Schema::create('plan_upgrade_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->foreignUuid('plan_id')->nullable()->constrained('saas_plans')->restrictOnDelete();   // the plan they would like, if they chose one
            $table->foreignUuid('from_plan_id')->nullable()->constrained('saas_plans')->restrictOnDelete();
            $table->string('status', 12)->default('requested');  // requested → handled | cancelled
            $table->uuid('requested_by')->nullable();            // users.id
            $table->text('note')->nullable();
            $table->uuid('handled_by')->nullable();              // platform_admins.id
            $table->timestampTz('handled_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
        });
        DB::statement("alter table plan_upgrade_requests add constraint plan_upgrade_requests_status check (status in ('requested', 'handled', 'cancelled'))");
        DB::statement("create unique index plan_upgrade_requests_one_open on plan_upgrade_requests (academy_id) where status = 'requested'");

        // A request is answered once: only a waiting one changes, to its answer, and what was asked stays as it was.
        DB::unprepared(<<<'SQL'
            create or replace function lms_usage_topups_forward() returns trigger language plpgsql as $$
            begin
                if old.status <> 'requested' or new.status = 'requested'
                   or (to_jsonb(new) - 'status' - 'period' - 'decided_by' - 'decided_at' - 'reason' - 'updated_at')
                      <> (to_jsonb(old) - 'status' - 'period' - 'decided_by' - 'decided_at' - 'reason' - 'updated_at')
                   or (new.period <> old.period and new.status <> 'active') then
                    raise exception 'an extra pack request is answered once, and what was asked stays as it was';
                end if;
                return new;
            end $$;
            create trigger lms_usage_topups_forward before update on usage_topups
                for each row execute function lms_usage_topups_forward();

            create or replace function lms_plan_upgrade_requests_forward() returns trigger language plpgsql as $$
            begin
                if old.status <> 'requested' or new.status = 'requested'
                   or (to_jsonb(new) - 'status' - 'handled_by' - 'handled_at' - 'updated_at')
                      <> (to_jsonb(old) - 'status' - 'handled_by' - 'handled_at' - 'updated_at') then
                    raise exception 'a plan upgrade request is answered once, and what was asked stays as it was';
                end if;
                return new;
            end $$;
            create trigger lms_plan_upgrade_requests_forward before update on plan_upgrade_requests
                for each row execute function lms_plan_upgrade_requests_forward();
            SQL);

        $this->seedPacks();

        DB::statement('alter table saas_topup_packs enable row level security');
        DB::statement('alter table saas_topup_packs force row level security');
        DB::statement('revoke all on saas_topup_packs from lms_app');
        TenantTable::enableRls('usage_topups');
        TenantTable::enableRls('plan_upgrade_requests');
        DB::statement('revoke insert, update, delete on usage_topups, plan_upgrade_requests from lms_app');
        DB::statement('revoke delete on usage_topups, plan_upgrade_requests from lms_platform');
    }

    /** The packs the platform starts with (the console edits them). */
    private function seedPacks(): void
    {
        $now = now();
        $packs = [
            ['video_minutes', 300, 'صغيرة', 'Small', 1], ['video_minutes', 600, 'متوسطة', 'Medium', 2], ['video_minutes', 1500, 'كبيرة', 'Large', 3],
            ['ai_uses', 100, 'صغيرة', 'Small', 1], ['ai_uses', 300, 'متوسطة', 'Medium', 2], ['ai_uses', 1000, 'كبيرة', 'Large', 3],
        ];
        foreach ($packs as [$limit, $amount, $ar, $en, $sort]) {
            DB::table('saas_topup_packs')->insert([
                'id' => (string) Str::uuid7(), 'limit' => $limit, 'amount' => $amount, 'name_ar' => $ar, 'name_en' => $en,
                'sort' => $sort, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_upgrade_requests');
        Schema::dropIfExists('usage_topups');
        DB::unprepared('drop function if exists lms_usage_topups_forward(); drop function if exists lms_plan_upgrade_requests_forward();');
        Schema::dropIfExists('saas_topup_packs');
        DB::statement('alter table saas_plans drop constraint if exists saas_plans_ai_uses');
        Schema::table('saas_plans', fn (Blueprint $table) => $table->dropColumn('ai_uses'));
    }
};
