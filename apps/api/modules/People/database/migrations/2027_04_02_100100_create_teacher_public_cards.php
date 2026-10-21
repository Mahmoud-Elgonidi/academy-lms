<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * A teacher's card on the academy's public page (docs/architecture/02,
 * section 24): written by the teacher themself (the name to show, a few
 * words, a photo on the public disk) and shown only while they switch it on
 * and the academy has not taken it off. Never a number or an email: the
 * page shows the card's own words and what the teacher's profile says they
 * teach (PeopleApi::publicTeachers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_public_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('teacher_id');
            $table->string('display_name', 60);
            $table->string('bio', 280)->nullable();
            $table->string('photo_path', 255)->nullable();   // public disk: site/{academy}/teachers/{uuid}.{ext}
            $table->boolean('shown')->default(false);         // the teacher's own switch
            $table->boolean('hidden_by_academy')->default(false);
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'teacher_id']);
            $table->tenantForeign('teacher_id', 'users')->cascadeOnDelete();
        });
        DB::statement('alter table teacher_public_cards add constraint teacher_public_cards_name check (length(trim(display_name)) > 0)');
        TenantTable::enableRls('teacher_public_cards');
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_public_cards');
    }
};
