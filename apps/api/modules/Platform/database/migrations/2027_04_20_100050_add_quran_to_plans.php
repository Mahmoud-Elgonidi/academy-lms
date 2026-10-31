<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The Quran became an area a plan may leave out (Feature::Quran,
 * docs/architecture/02 section 26). Every academy had it until now, so every
 * plan there is takes it; the console leaves it out of a plan from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table saas_plans no force row level security');
        DB::statement(<<<'SQL'
            update saas_plans set features = features || '["quran"]'::jsonb
            where not jsonb_exists(features, 'quran')
        SQL);
        DB::statement('alter table saas_plans force row level security');
    }

    public function down(): void
    {
        DB::statement('alter table saas_plans no force row level security');
        DB::statement("update saas_plans set features = features - 'quran'");
        DB::statement('alter table saas_plans force row level security');
    }
};
