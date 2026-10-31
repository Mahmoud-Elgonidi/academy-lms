<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Group lessons (docs/architecture/02, "الحصص الجماعية"): a plan is for
 * private lessons (one student) or group lessons; which is part of its
 * shape, fixed once anyone is on it. A group plan is never a shared package.
 * A course that joins a group after it began may have its first block's
 * missed lessons taken off (late_join_lessons, block 1 only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('lesson_format', 10)->default('private');   // private | group
        });
        DB::statement("alter table plans add constraint plans_lesson_format_check check (lesson_format in ('private', 'group'))");
        DB::statement("alter table plans add constraint plans_group_not_shared check (not (lesson_format = 'group' and credit_scope = 'student_pool'))");

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_join_lessons')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('late_join_lessons'));
        DB::statement('alter table plans drop constraint if exists plans_group_not_shared');
        DB::statement('alter table plans drop constraint if exists plans_lesson_format_check');
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('lesson_format'));
    }
};
