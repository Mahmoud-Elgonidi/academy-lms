<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

return new class extends Migration
{
    public function up(): void
    {
        // Levels and categories come with the Catalog screens (P1).
        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->timestampsTz();

            $table->tenantKey();
        });

        TenantTable::enableRls('subjects');
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
