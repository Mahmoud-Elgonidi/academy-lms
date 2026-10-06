<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The curriculum (docs/architecture/02-domain-rules.md, section 6): a program
 * per subject, versions, and a tree of levels, units and lessons. The MVP
 * keeps one published version per program; a published lesson is never
 * deleted, because progress points at it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('subject_id');
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('status', 10)->default('active'); // active | archived
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'subject_id']);
            $table->tenantForeign('subject_id', 'subjects');
        });
        TenantTable::enableRls('programs');

        Schema::create('curriculum_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('program_id');
            $table->unsignedInteger('version');
            $table->string('status', 10)->default('draft'); // draft | published | retired
            $table->timestampTz('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'program_id', 'version']);
            $table->tenantForeign('program_id', 'programs')->cascadeOnDelete();
        });
        DB::statement("create unique index curriculum_versions_one_published on curriculum_versions (academy_id, program_id) where status = 'published'");
        TenantTable::enableRls('curriculum_versions');

        Schema::create('curriculum_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('version_id');
            $table->uuid('parent_id')->nullable();
            $table->string('kind', 10);                     // level | unit | lesson
            $table->unsignedInteger('ordinal');
            $table->string('title_ar', 200);
            $table->string('title_en', 200);
            $table->text('objectives')->nullable();
            $table->unsignedSmallInteger('weight')->default(1);       // share of the program, for the progress %
            $table->unsignedSmallInteger('est_sessions')->default(1); // sessions it usually takes
            $table->string('mastery_rule', 20)->default('mastered_once'); // mastered_once | score_twice
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'version_id', 'parent_id', 'ordinal']);
            $table->tenantForeign('version_id', 'curriculum_versions')->cascadeOnDelete();
            $table->tenantForeign('parent_id', 'curriculum_nodes')->cascadeOnDelete();
        });
        DB::statement("alter table curriculum_nodes add constraint curriculum_nodes_kind check (kind in ('level', 'unit', 'lesson'))");
        TenantTable::enableRls('curriculum_nodes');
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_nodes');
        Schema::dropIfExists('curriculum_versions');
        Schema::dropIfExists('programs');
    }
};
