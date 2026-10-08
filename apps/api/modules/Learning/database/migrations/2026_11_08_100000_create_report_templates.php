<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * A report template per subject (docs/architecture/01, section 10: "قالب
 * حسب المادة بإصدارات"): the academy's own questions on top of the report
 * every lesson has (pages memorised, tajweed mistakes, new words…). Each
 * change is a new version, never an edit, so a report keeps the questions
 * it was written with; its answers sit on the report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('subject_id');
            $table->unsignedInteger('version');
            $table->jsonb('fields');                    // list of {key, label_ar, label_en, type, required, family, options?, min?, max?, unit_ar?, unit_en?}
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at');

            $table->tenantKey();
            $table->unique(['academy_id', 'subject_id', 'version']);
            $table->tenantForeign('subject_id', 'subjects');
        });
        DB::statement("alter table report_templates add constraint report_templates_fields check (jsonb_typeof(fields) = 'array' and jsonb_array_length(fields) <= 12)");
        TenantTable::enableRls('report_templates');
        DB::statement('revoke update, delete on report_templates from lms_app');

        Schema::table('session_reports', function (Blueprint $table) {
            $table->uuid('template_id')->nullable();
            $table->jsonb('answers')->default('{}');
            $table->tenantForeign('template_id', 'report_templates');
        });
    }

    public function down(): void
    {
        Schema::table('session_reports', function (Blueprint $table) {
            $table->dropForeign(['academy_id', 'template_id']);
            $table->dropColumn(['template_id', 'answers']);
        });
        Schema::dropIfExists('report_templates');
    }
};
