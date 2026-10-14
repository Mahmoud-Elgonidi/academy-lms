<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Pausing and ending a course (docs/architecture/02, section 3, "إيقاف
 * الكورس وإنهاؤه"): days on the academy's calendar. A pause is from its
 * first day to its last, both included; it may lie ahead (the course is
 * paused from that day) and is cleared when the course is on again. A
 * course ends from its first day without lessons, today or later. The
 * reasons are the office's words, never shown to the family.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->date('paused_from')->nullable()->after('start_date');
            $table->date('paused_until')->nullable()->after('paused_from');
            $table->string('pause_reason', 500)->nullable()->after('paused_until');
            $table->date('ends_on')->nullable()->after('pause_reason');
            $table->string('end_reason', 500)->nullable()->after('ends_on');
        });
        DB::statement('alter table enrollments add constraint enrollments_pause check ((paused_from is null) = (paused_until is null) and (paused_until is null or paused_until >= paused_from))');
        // The daily look at courses whose days come (scheduling:courses) reads only these.
        DB::statement('create index enrollments_with_days on enrollments (academy_id) where paused_from is not null or ends_on is not null');
    }

    public function down(): void
    {
        DB::statement('drop index if exists enrollments_with_days');
        DB::statement('alter table enrollments drop constraint if exists enrollments_pause');
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['paused_from', 'paused_until', 'pause_reason', 'ends_on', 'end_reason']);
        });
    }
};
