<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Group lessons (docs/architecture/02, "الحصص الجماعية"): whether a teacher
 * takes groups, and the most students one of their lessons may hold (2–20).
 * Private lessons stay as they are (accepts_new).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->boolean('accepts_groups')->default(false);
            $table->unsignedSmallInteger('max_group_size')->nullable();
        });
        DB::statement('alter table teacher_profiles add constraint teacher_profiles_group_size check ((max_group_size is null or max_group_size between 2 and 20) and (not accepts_groups or max_group_size is not null))');
    }

    public function down(): void
    {
        DB::statement('alter table teacher_profiles drop constraint if exists teacher_profiles_group_size');
        Schema::table('teacher_profiles', fn (Blueprint $table) => $table->dropColumn(['accepts_groups', 'max_group_size']));
    }
};
