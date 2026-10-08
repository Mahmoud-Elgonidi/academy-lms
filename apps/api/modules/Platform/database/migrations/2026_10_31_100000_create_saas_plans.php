<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The platform's SaaS plans (docs/architecture/01: "باقات SaaS للأكاديميات،
 * بحدود ... وميزات بتتفتح وتتقفل"). A plan says how many teachers and active
 * students an academy may have, its video minutes a month and its storage
 * (null: no limit), and which areas it includes. Each academy is on one plan
 * at a time (saas_subscriptions: a row per plan it has been on, with the
 * platform team's overrides for this academy), and usage_counters keeps the
 * daily count of each limit for the console and the academy's warning.
 *
 * Only the platform writes these. The plans table is read on the platform
 * connection alone; an academy may read its own subscription and counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->citext('code')->unique();
            $table->string('name_ar', 80);
            $table->string('name_en', 80);
            $table->integer('max_teachers')->nullable();
            $table->integer('max_active_students')->nullable();
            $table->integer('video_minutes')->nullable();          // a calendar month, on the academy's clock
            $table->integer('storage_mb')->nullable();
            $table->jsonb('features')->default('[]');               // SharedKernel\Plans\Feature values
            $table->bigInteger('price_minor')->nullable();          // what the platform charges a month, for the console
            $table->char('currency', 3)->nullable();
            $table->boolean('is_default')->default(false);          // new academies start on it
            $table->smallInteger('sort')->default(0);
            $table->timestampTz('archived_at')->nullable();         // no new academy on it; those on it stay
            $table->timestampsTz();
        });
        DB::statement('alter table saas_plans add constraint saas_plans_limits check (coalesce(max_teachers, 0) >= 0 and coalesce(max_active_students, 0) >= 0 and coalesce(video_minutes, 0) >= 0 and coalesce(storage_mb, 0) >= 0)');
        DB::statement("alter table saas_plans add constraint saas_plans_features check (jsonb_typeof(features) = 'array')");
        DB::statement('alter table saas_plans add constraint saas_plans_price check ((price_minor is null) = (currency is null) and coalesce(price_minor, 0) >= 0)');
        DB::statement('create unique index saas_plans_one_default on saas_plans ((true)) where is_default and archived_at is null');

        Schema::create('saas_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->foreignUuid('plan_id')->constrained('saas_plans')->restrictOnDelete();
            $table->jsonb('limits')->default('{}');                 // this academy's own limits: {"teachers": 25}, null for none
            $table->jsonb('features_on')->default('[]');            // areas added for this academy
            $table->jsonb('features_off')->default('[]');           // areas taken away
            $table->text('reason')->nullable();                     // why the platform team changed it
            $table->uuid('changed_by')->nullable();                 // platform_admins.id (null: the system)
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement("alter table saas_subscriptions add constraint saas_subscriptions_shape check (jsonb_typeof(limits) = 'object' and jsonb_typeof(features_on) = 'array' and jsonb_typeof(features_off) = 'array')");
        DB::statement('create unique index saas_subscriptions_one_current on saas_subscriptions (academy_id) where ended_at is null');

        // A plan an academy was on stays as it was: the only change is ending it, once.
        DB::unprepared(<<<'SQL'
            create or replace function lms_saas_subscriptions_keep() returns trigger language plpgsql as $$
            begin
                if old.ended_at is not null or new.ended_at is null
                   or (to_jsonb(new) - 'ended_at' - 'updated_at') <> (to_jsonb(old) - 'ended_at' - 'updated_at') then
                    raise exception 'a plan an academy was on is kept as it was; only ending it is allowed';
                end if;
                return new;
            end $$;
            create trigger lms_saas_subscriptions_keep before update on saas_subscriptions
                for each row execute function lms_saas_subscriptions_keep();
            SQL);

        Schema::create('usage_counters', function (Blueprint $table) {
            $table->tenant();
            $table->string('metric', 30);                           // SharedKernel\Plans\Limit values
            $table->date('period');                                 // the month's first day, on the academy's clock
            $table->bigInteger('value');
            $table->integer('max')->nullable();                     // the limit when counted
            $table->timestampTz('measured_at');
            $table->primary(['academy_id', 'metric', 'period']);
        });

        $this->seedPlans();

        DB::statement('alter table saas_plans enable row level security');
        DB::statement('alter table saas_plans force row level security');
        TenantTable::enableRls('saas_subscriptions');
        TenantTable::enableRls('usage_counters');
        DB::statement('revoke insert, update, delete on saas_subscriptions, usage_counters from lms_app');
        DB::statement('revoke delete on saas_subscriptions from lms_platform');
    }

    /**
     * The plans the platform starts with (the console edits them). Academies
     * that were here before plans keep everything, on "unlimited".
     */
    private function seedPlans(): void
    {
        $now = now();
        $plans = [
            ['starter', 'البداية', 'Starter', 5, 50, 3000, 5 * 1024, [], true, 1],
            ['growth', 'النمو', 'Growth', 20, 250, 15000, 25 * 1024, ['crm', 'online_payments', 'import', 'custom_branding'], false, 2],
            ['pro', 'الاحتراف', 'Pro', 75, 1000, 60000, 100 * 1024, ['crm', 'online_payments', 'import', 'custom_branding', 'payroll'], false, 3],
            ['unlimited', 'مفتوحة', 'Unlimited', null, null, null, null, ['crm', 'online_payments', 'import', 'custom_branding', 'payroll'], false, 4],
        ];
        $ids = [];
        foreach ($plans as [$code, $ar, $en, $teachers, $students, $minutes, $storage, $features, $default, $sort]) {
            $ids[$code] = (string) Str::uuid7();
            DB::table('saas_plans')->insert([
                'id' => $ids[$code], 'code' => $code, 'name_ar' => $ar, 'name_en' => $en,
                'max_teachers' => $teachers, 'max_active_students' => $students, 'video_minutes' => $minutes, 'storage_mb' => $storage,
                'features' => json_encode($features), 'is_default' => $default, 'sort' => $sort, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // The owner reads every academy only with its own row-level security set aside for a moment.
        DB::statement('alter table academies no force row level security');
        foreach (DB::table('academies')->pluck('id') as $academyId) {
            DB::table('saas_subscriptions')->insert([
                'id' => (string) Str::uuid7(), 'academy_id' => $academyId, 'plan_id' => $ids['unlimited'],
                'reason' => 'Before plans', 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::statement('alter table academies force row level security');
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('saas_subscriptions');
        DB::unprepared('drop function if exists lms_saas_subscriptions_keep()');
        Schema::dropIfExists('saas_plans');
    }
};
