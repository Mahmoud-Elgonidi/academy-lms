<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The family's journey, asking and booking the trial (docs/architecture/02,
 * section 27). The academy chooses who books a free trial asked for on its
 * page (the family itself, or its staff), how soon it promises to answer,
 * and the subject a family that wants help choosing starts with. A request
 * says when the family is free (days and hours on their clock) and the
 * teacher they would rather have; it knows the trial booked from it, and
 * whether the CRM could not make a lead of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('trial_booking', 8)->default('family');          // family | staff
            $table->unsignedSmallInteger('response_hours')->default(4);       // how soon the academy answers a request
            $table->unsignedSmallInteger('trial_notice_hours')->default(3);   // a family books its trial at least this long ahead
            $table->uuid('default_trial_subject_id')->nullable();            // "help me choose": the trial's subject
        });
        DB::statement("alter table site_settings add constraint site_settings_trial_booking check (trial_booking in ('family', 'staff'))");
        DB::statement('alter table site_settings add constraint site_settings_response_hours check (response_hours between 1 and 72)');
        DB::statement('alter table site_settings add constraint site_settings_trial_notice check (trial_notice_hours between 1 and 72)');
        DB::statement('alter table site_settings add constraint site_settings_trial_subject_fk foreign key (academy_id, default_trial_subject_id) references subjects (academy_id, id) on delete set null (default_trial_subject_id)');

        Schema::table('site_requests', function (Blueprint $table) {
            $table->jsonb('preferred_days')->default('[]');                   // ISO weekdays, on the visitor's clock
            $table->unsignedSmallInteger('preferred_from')->nullable();       // hour of day, visitor's clock
            $table->unsignedSmallInteger('preferred_to')->nullable();
            $table->string('teacher_gender', 6)->nullable();                  // female | male: the teacher they would rather have
            $table->boolean('help_choose')->default(false);                   // asked the academy to choose the subject
            $table->uuid('session_id')->nullable();                           // the trial the family booked from it
            $table->timestampTz('lead_failed_at')->nullable();                // the CRM could not make a lead of it
            $table->string('lead_failure', 20)->nullable();                   // invalid | error
        });
        DB::statement("alter table site_requests add constraint site_requests_days check (jsonb_typeof(preferred_days) = 'array')");
        DB::statement('alter table site_requests add constraint site_requests_hours check (preferred_from is null or preferred_from between 0 and 23) ');
        DB::statement('alter table site_requests add constraint site_requests_hours_to check (preferred_to is null or preferred_to between 1 and 24)');
        DB::statement("alter table site_requests add constraint site_requests_gender check (teacher_gender is null or teacher_gender in ('female', 'male'))");
        DB::statement("alter table site_requests add constraint site_requests_failure check (lead_failure is null or lead_failure in ('invalid', 'error'))");
        DB::statement('alter table site_requests add constraint site_requests_session_fk foreign key (academy_id, session_id) references sessions (academy_id, id) on delete set null (session_id)');
    }

    public function down(): void
    {
        DB::statement('alter table site_requests drop constraint if exists site_requests_session_fk');
        Schema::table('site_requests', function (Blueprint $table) {
            $table->dropColumn(['preferred_days', 'preferred_from', 'preferred_to', 'teacher_gender', 'help_choose', 'session_id', 'lead_failed_at', 'lead_failure']);
        });
        DB::statement('alter table site_settings drop constraint if exists site_settings_trial_subject_fk');
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['trial_booking', 'response_hours', 'trial_notice_hours', 'default_trial_subject_id']);
        });
    }
};
