<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

return new class extends Migration
{
    public function up(): void
    {
        // The look an academy uses now. No row means the default built-in template.
        Schema::create('academy_branding', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            // "builtin:noor" or "saved:{uuid}" when the tokens came from a template;
            // null once they are edited away from any template.
            $table->string('template_ref', 64)->nullable();
            $table->jsonb('tokens');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->unique('academy_id');
            $table->tenantKey();
        });

        TenantTable::enableRls('academy_branding');

        // Looks an academy saved from its own edits ("save as template").
        Schema::create('branding_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('name', 60);
            $table->jsonb('tokens');
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->unique(['academy_id', 'name']);
            $table->tenantKey();
        });

        TenantTable::enableRls('branding_templates');
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_templates');
        Schema::dropIfExists('academy_branding');
    }
};
