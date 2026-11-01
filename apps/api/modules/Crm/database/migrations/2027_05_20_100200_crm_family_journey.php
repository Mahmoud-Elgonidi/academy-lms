<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The family's journey in the CRM (docs/architecture/02, section 27): the
 * teacher a lead would rather have, and the academy's lead settings (one row
 * per academy): website leads shared out in turn among those who work leads
 * (on at first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('teacher_gender', 6)->nullable();     // female | male: the teacher they would rather have
        });
        DB::statement("alter table leads add constraint leads_teacher_gender check (teacher_gender is null or teacher_gender in ('female', 'male'))");

        Schema::create('crm_settings', function (Blueprint $table) {
            $table->tenant();
            $table->boolean('assign_website_leads')->default(true);
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->primary('academy_id');
            $table->tenantForeign('updated_by', 'users');
        });
        TenantTable::enableRls('crm_settings');
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_settings');
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('teacher_gender'));
    }
};
