<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

return new class extends Migration
{
    public function up(): void
    {
        // A subject's levels, in order (docs/architecture/01, section 3): a
        // teacher teaches a range of them, and "Find a teacher" asks for one.
        Schema::create('subject_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('subject_id');
            $table->unsignedSmallInteger('ordinal');
            $table->string('name_ar', 80);
            $table->string('name_en', 80);
            $table->timestampsTz();

            $table->tenantKey();
            // What a teacher's range points at: a level of that same subject.
            $table->unique(['academy_id', 'subject_id', 'id']);
            $table->tenantForeign('subject_id', 'subjects')->cascadeOnDelete();
        });

        // A subject's own categories: its tracks (memorising, tajweed,
        // conversation) or the school curricula it follows (British,
        // American, Saudi). A teacher takes the ones they teach.
        Schema::create('subject_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('subject_id');
            $table->unsignedSmallInteger('ordinal');
            $table->string('name_ar', 80);
            $table->string('name_en', 80);
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'subject_id', 'id']);
            $table->tenantForeign('subject_id', 'subjects')->cascadeOnDelete();
        });

        foreach (['subject_levels', 'subject_categories'] as $name) {
            // One place per position; checked at commit, so a list can be put in a new order.
            DB::statement("alter table {$name} add constraint {$name}_order unique (academy_id, subject_id, ordinal) deferrable initially deferred");
            TenantTable::enableRls($name);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_categories');
        Schema::dropIfExists('subject_levels');
    }
};
