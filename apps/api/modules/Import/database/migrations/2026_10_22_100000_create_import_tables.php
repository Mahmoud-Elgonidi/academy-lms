<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Imports from an academy's spreadsheets (docs/architecture/01: "استيراد
 * من CSV/Excel"): each file is a run, kept with its rows, how its columns
 * were read, what a dry run said of each row and what the import did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('kind', 20);                     // teachers | families | schedules
            $table->string('file_name', 200);
            $table->string('status', 12)->default('staged'); // staged | checked | done
            $table->jsonb('headers');                       // the file's column titles, in order
            $table->jsonb('mapping')->default('{}');        // field => column index
            $table->jsonb('options')->default('{}');        // country code, time zone, invitations
            $table->jsonb('counts')->default('{}');         // status => rows
            $table->uuid('created_by');
            $table->timestampTz('checked_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'created_at']);
            $table->tenantForeign('created_by', 'users');
        });
        TenantTable::enableRls('import_runs');

        Schema::create('import_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('run_id');
            $table->unsignedInteger('line');                // the row in the file (headers are line 1)
            $table->jsonb('cells');                         // the values, by column index
            $table->string('status', 12)->default('pending'); // pending | new | update | skip | error | created | updated | skipped | failed
            $table->text('message')->nullable();
            $table->jsonb('refs')->default('{}');           // what it made: user ids, enrollment id
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'run_id', 'line']);
            $table->tenantForeign('run_id', 'import_runs')->cascadeOnDelete();
        });
        TenantTable::enableRls('import_rows');
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_runs');
    }
};
