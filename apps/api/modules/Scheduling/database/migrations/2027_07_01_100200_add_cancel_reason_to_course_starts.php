<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Why a course the family started on its own was called off (docs/architecture/02,
 * section 27, J5a): `family` (CancelOwnStart, before paying) or `unpaid` (it
 * closed itself: CloseUnpaidStarts, its pay-by passed and rule 16 cancelled
 * its first lesson). Earlier calls-off are the family's.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table course_starts add column cancel_reason varchar(10) null');
        DB::statement("update course_starts set cancel_reason = 'family' where cancelled_at is not null");
        DB::statement("alter table course_starts add constraint course_starts_cancel_reason check (cancel_reason is null or cancel_reason in ('family', 'unpaid'))");
    }

    public function down(): void
    {
        DB::statement('alter table course_starts drop constraint if exists course_starts_cancel_reason');
        DB::statement('alter table course_starts drop column if exists cancel_reason');
    }
};
