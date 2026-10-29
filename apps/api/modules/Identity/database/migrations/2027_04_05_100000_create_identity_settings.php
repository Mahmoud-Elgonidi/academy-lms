<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * How the academy's people sign in (docs/architecture/02, section 19): whether
 * two-step sign-in is required of the owner, the admins and the accountants
 * (on unless the owner turns it off; SaveSecuritySettings). One row per
 * academy, none until the owner saves; nobody's personal data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_settings', function (Blueprint $table) {
            $table->tenant();
            $table->boolean('require_two_factor')->default(true);
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        TenantTable::enableRls('identity_settings');
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_settings');
    }
};
