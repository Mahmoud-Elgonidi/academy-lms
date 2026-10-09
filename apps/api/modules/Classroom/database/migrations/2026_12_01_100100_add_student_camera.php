<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A student's camera is theirs to turn on (docs/architecture/03, "الأطفال
 * وسلامتهم"): the academy chooses whether students come into a lesson with it
 * on (as before), off, or off for students under 18; they can always turn it
 * on or off themselves, and nobody needs it to take part.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_settings', function (Blueprint $table) {
            $table->string('student_camera', 16)->default('on');   // on | off | off_for_minors
        });
        DB::statement("alter table classroom_settings add constraint classroom_settings_student_camera check (student_camera in ('on', 'off', 'off_for_minors'))");
    }

    public function down(): void
    {
        Schema::table('classroom_settings', fn (Blueprint $table) => $table->dropColumn('student_camera'));
    }
};
