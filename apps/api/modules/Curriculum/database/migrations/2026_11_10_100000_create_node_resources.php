<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * A lesson's resources (docs/architecture/01, section 11: "في كل درس أهداف
 * وموارد"): a file the academy uploads (PDF, a recording, a short video, a
 * picture) on the private disk, or a link (a video on YouTube, a page). Each
 * is for everyone who follows the lesson, or for its teachers only (a
 * teacher's guide, answers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('node_id');
            $table->unsignedInteger('ordinal');
            $table->string('kind', 10);                     // file | link
            $table->string('title_ar', 160);
            $table->string('title_en', 160);
            $table->string('audience', 10)->default('all'); // all | teachers
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('url')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'node_id', 'ordinal']);
            $table->tenantForeign('node_id', 'curriculum_nodes')->cascadeOnDelete();
        });
        DB::statement("alter table node_resources add constraint node_resources_kind check (
            (kind = 'file' and file_path is not null and url is null) or (kind = 'link' and url is not null and file_path is null))");
        DB::statement("alter table node_resources add constraint node_resources_audience check (audience in ('all', 'teachers'))");
        TenantTable::enableRls('node_resources');
    }

    public function down(): void
    {
        Schema::dropIfExists('node_resources');
    }
};
