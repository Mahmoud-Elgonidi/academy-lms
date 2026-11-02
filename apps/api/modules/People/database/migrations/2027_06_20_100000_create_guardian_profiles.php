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
        // What the academy knows of a guardian beyond their account: the country
        // they live in, which a family's prices are in when they pay for a learner
        // who has none of their own (docs/architecture/02, section 27, J3).
        Schema::create('guardian_profiles', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('user_id');
            $table->char('country', 2)->nullable();
            $table->timestampsTz();

            $table->primary(['academy_id', 'user_id']);
            $table->tenantForeign('user_id', 'users')->cascadeOnDelete();
        });
        DB::statement("alter table guardian_profiles add constraint guardian_profiles_country check (country is null or country ~ '^[A-Z]{2}$')");
        TenantTable::enableRls('guardian_profiles');
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_profiles');
    }
};
