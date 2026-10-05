<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->citext('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('status', 20)->default('active');
            $table->string('default_timezone', 64)->default('UTC');
            $table->string('default_locale', 5)->default('ar');
            $table->char('base_currency', 3);
            $table->char('country', 2)->nullable();
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();
        });

        // The app role sees only its own academy row; platform code uses the
        // BYPASSRLS connection to look academies up by host or slug.
        TenantTable::enableRls('academies', 'id');

        Schema::create('academy_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->citext('host')->unique();
            $table->string('kind', 20); // subdomain | custom
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
        });

        TenantTable::enableRls('academy_domains');
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_domains');
        Schema::dropIfExists('academies');
    }
};
