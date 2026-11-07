<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The academy's modules (docs/architecture/02, "وحدات الأكاديمية"). The free
 * areas (group lessons, the curriculum and journey, games, messages,
 * announcements, help, teachers' performance, the academy's page) became
 * areas a plan has, and every plan there is takes them, so nothing
 * disappears on deploy. The academy may hide any of them: one row per
 * academy with what it hid (Feature values), saved only through
 * SaveAcademyModules. Hiding never deletes anything.
 */
return new class extends Migration
{
    private const OPTIONAL = ['groups', 'curriculum', 'games', 'messaging', 'announcements', 'support', 'evaluation', 'site'];

    public function up(): void
    {
        Schema::create('academy_modules', function (Blueprint $table) {
            $table->tenant();
            $table->jsonb('hidden')->default('[]');      // optional Feature values the academy hid
            $table->uuid('updated_by')->nullable();       // the staff member who saved it last
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        DB::statement("alter table academy_modules add constraint academy_modules_hidden check (jsonb_typeof(hidden) = 'array')");
        TenantTable::enableRls('academy_modules');

        DB::statement('alter table saas_plans no force row level security');
        foreach (self::OPTIONAL as $feature) {
            DB::update('update saas_plans set features = features || jsonb_build_array(?::text) where not jsonb_exists(features, ?)', [$feature, $feature]);
        }
        DB::statement('alter table saas_plans force row level security');
    }

    public function down(): void
    {
        DB::statement('alter table saas_plans no force row level security');
        foreach (self::OPTIONAL as $feature) {
            DB::update('update saas_plans set features = features - ?::text', [$feature]);
        }
        DB::statement('alter table saas_plans force row level security');
        Schema::dropIfExists('academy_modules');
    }
};
