<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The cancellation, makeup and absence policy (docs/architecture/02, section 4).
 * Every change to the numbers is a new version; each decision on a session
 * records the version it was made under, so a dispute reads the rules that
 * applied then. One policy per academy for now; per-plan policies later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->unsignedInteger('version');
            $table->jsonb('settings');
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at');

            $table->tenantKey();
            $table->unique(['academy_id', 'version']);
        });
        TenantTable::enableRls('policy_versions');
        DB::statement('revoke update, delete on policy_versions from lms_app');

        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('policy_version_id')->nullable()->after('decision');
            $table->unsignedSmallInteger('reschedule_count')->default(0)->after('policy_version_id');
            $table->boolean('is_detached')->default(false)->after('reschedule_count'); // moved by hand: no longer where its weekly time puts it

            $table->tenantForeign('policy_version_id', 'policy_versions');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropForeign(['academy_id', 'policy_version_id']);
            $table->dropColumn(['policy_version_id', 'reschedule_count', 'is_detached']);
        });
        Schema::dropIfExists('policy_versions');
    }
};
