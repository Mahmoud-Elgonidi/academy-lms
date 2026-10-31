<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Group lessons (docs/architecture/02, "الحصص الجماعية"): a group lesson
 * asks its teacher for one report per student in it (the class's shared
 * part written once, a rating and a note for each). One report per lesson
 * becomes one per lesson and student; a one-to-one lesson keeps its one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table session_reports drop constraint if exists session_reports_academy_id_session_id_unique');
        DB::statement('create unique index session_reports_one_per_student on session_reports (academy_id, session_id, student_id)');
    }

    public function down(): void
    {
        DB::statement('drop index if exists session_reports_one_per_student');
        DB::statement('alter table session_reports add constraint session_reports_academy_id_session_id_unique unique (academy_id, session_id)');
    }
};
