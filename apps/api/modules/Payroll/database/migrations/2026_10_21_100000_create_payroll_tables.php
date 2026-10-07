<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Payroll (docs/architecture/01, section 13; 02, "مرتبات المدرسين"): what a
 * teacher is paid for each lesson, by rate card, kept as a ledger that is
 * only ever added to; months that are closed take no more entries (held by
 * the database), so a month's export never changes once it is closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('name', 80);
            $table->char('currency', 3);
            $table->boolean('is_default')->default(false);  // for teachers who have no card of their own
            $table->timestampsTz();

            $table->tenantKey();
        });
        DB::statement('create unique index rate_cards_one_default on rate_cards (academy_id) where is_default');
        TenantTable::enableRls('rate_cards');

        // A price for a subject and a kind of lesson (either may be "any"); the most specific line wins.
        Schema::create('rate_card_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('rate_card_id');
            $table->uuid('subject_id')->nullable();
            $table->string('session_type', 20)->nullable();
            $table->string('unit', 10);                     // hour (by the minute) | lesson
            $table->bigInteger('amount_minor');
            $table->timestampsTz();

            $table->tenantKey();
            $table->tenantForeign('rate_card_id', 'rate_cards')->cascadeOnDelete();
            $table->tenantForeign('subject_id', 'subjects');
        });
        DB::statement('create unique index rate_card_lines_once on rate_card_lines (academy_id, rate_card_id, subject_id, session_type) nulls not distinct');
        DB::statement("alter table rate_card_lines add constraint rate_card_lines_unit check (unit in ('hour', 'lesson'))");
        DB::statement('alter table rate_card_lines add constraint rate_card_lines_amount check (amount_minor >= 0)');
        TenantTable::enableRls('rate_card_lines');

        Schema::create('teacher_rate_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('teacher_id');
            $table->uuid('rate_card_id');
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'teacher_id']);
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('rate_card_id', 'rate_cards')->cascadeOnDelete();
        });
        TenantTable::enableRls('teacher_rate_cards');

        // A closed month: a row here means no more entries for it.
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->date('month');                          // the first day, in the academy's time zone
            $table->timestampTz('closed_at');
            $table->uuid('closed_by');
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'month']);
            $table->tenantForeign('closed_by', 'users');
        });
        TenantTable::enableRls('payroll_periods');

        Schema::create('teacher_earnings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('teacher_id');
            $table->date('period');                         // the month it is paid in (first day)
            $table->string('kind', 12);                     // lesson | reversal | bonus | deduction
            $table->uuid('session_id')->nullable();
            $table->uuid('reverses_id')->nullable();
            $table->date('lesson_on')->nullable();          // the lesson's day in the academy's time zone
            $table->string('outcome', 20)->nullable();      // completed | student_absent | cancelled
            $table->unsignedSmallInteger('rule')->nullable();
            $table->string('session_type', 20)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->unsignedInteger('booked_min')->nullable();
            $table->unsignedSmallInteger('pay_pct')->nullable();
            $table->string('unit', 10)->nullable();
            $table->bigInteger('rate_minor')->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->uuid('rate_card_id')->nullable();       // the card it was priced from (a snapshot: the card may change)
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at');

            $table->tenantKey();
            $table->index(['academy_id', 'period', 'teacher_id']);
            $table->index(['academy_id', 'session_id']);
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('session_id', 'sessions');
            $table->tenantForeign('reverses_id', 'teacher_earnings');
            $table->tenantForeign('created_by', 'users');
        });
        DB::statement('create unique index teacher_earnings_reversed_once on teacher_earnings (academy_id, reverses_id) where reverses_id is not null');
        DB::statement("alter table teacher_earnings add constraint teacher_earnings_kind check (kind in ('lesson', 'reversal', 'bonus', 'deduction'))");
        DB::statement("alter table teacher_earnings add constraint teacher_earnings_sign check (
            (kind = 'bonus' and amount_minor > 0) or (kind = 'deduction' and amount_minor < 0) or (kind = 'lesson' and amount_minor >= 0) or kind = 'reversal')");
        DB::statement("alter table teacher_earnings add constraint teacher_earnings_reversal check ((kind = 'reversal') = (reverses_id is not null))");
        DB::statement('alter table teacher_earnings add constraint teacher_earnings_period check (extract(day from period) = 1)');
        TenantTable::enableRls('teacher_earnings');

        // The ledger is only added to, and never to a closed month.
        DB::unprepared(<<<'SQL'
            create or replace function lms_teacher_earnings_guard() returns trigger language plpgsql as $$
            begin
                if tg_op <> 'INSERT' then
                    raise exception 'teacher_earnings is append-only' using errcode = 'P0001';
                end if;
                if exists (select 1 from payroll_periods p where p.academy_id = new.academy_id and p.month = new.period) then
                    raise exception 'payroll month % is closed', to_char(new.period, 'YYYY-MM') using errcode = 'P0001';
                end if;
                return new;
            end $$;
            create trigger teacher_earnings_guard before insert or update or delete on teacher_earnings
                for each row execute function lms_teacher_earnings_guard();
            SQL);
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists teacher_earnings_guard on teacher_earnings');
        DB::statement('drop function if exists lms_teacher_earnings_guard()');
        Schema::dropIfExists('teacher_earnings');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('teacher_rate_cards');
        Schema::dropIfExists('rate_card_lines');
        Schema::dropIfExists('rate_cards');
    }
};
