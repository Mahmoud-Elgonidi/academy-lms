<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The CRM (docs/architecture/01, section 14 and flow "أ"; 02, the trial
 * lesson): a lead from first contact to a subscription or a loss, what
 * happened along the way, and its trial lessons with the teacher's
 * assessment. The learner and the contact become users only when a trial or
 * an enrolment needs them (so their links, messages and the class work).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('contact_name', 120);            // the parent, or the learner when an adult
            $table->string('phone_e164', 20);
            $table->string('email', 190)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('timezone', 64);
            $table->string('locale', 5)->default('ar');
            $table->boolean('for_child')->default(false);
            $table->string('learner_name', 120)->nullable(); // when for a child
            $table->unsignedSmallInteger('learner_birth_year')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('preferred_days')->default('[]');  // ISO weekdays, on the lead's clock
            $table->unsignedSmallInteger('preferred_from')->nullable();  // hour of day, lead's clock
            $table->unsignedSmallInteger('preferred_to')->nullable();
            $table->string('source', 20);                   // whatsapp | facebook | instagram | google | tiktok | website | referral | walk_in | other
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('owner_id')->nullable();           // the salesperson following it
            $table->string('stage', 16)->default('new');    // new | contacted | trial_booked | attended | assessed | won | lost
            $table->timestampTz('stage_changed_at')->useCurrent();
            $table->string('lost_reason', 20)->nullable();
            $table->timestampTz('last_contact_at')->nullable();
            $table->uuid('learner_id')->nullable();         // users, once a trial or enrolment needs them
            $table->uuid('contact_id')->nullable();
            $table->uuid('enrollment_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'stage', 'stage_changed_at']);
            $table->index(['academy_id', 'phone_e164']);
            $table->index(['academy_id', 'learner_id']);
            $table->tenantForeign('subject_id', 'subjects');
            $table->tenantForeign('owner_id', 'users');
            $table->tenantForeign('learner_id', 'users');
            $table->tenantForeign('contact_id', 'users');
            $table->tenantForeign('enrollment_id', 'enrollments');
        });
        DB::statement("alter table leads add constraint leads_stage check (stage in ('new', 'contacted', 'trial_booked', 'attended', 'assessed', 'won', 'lost'))");
        DB::statement("alter table leads add constraint leads_phone check (phone_e164 ~ '^\\+[1-9][0-9]{6,14}$')");
        TenantTable::enableRls('leads');

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('lead_id');
            $table->string('kind', 20);                     // created | call | whatsapp | note | assigned | stage | trial_booked | trial_moved | trial_cancelled | trial_attended | trial_missed | assessed | won | lost | reopened | edited
            $table->text('body')->nullable();
            $table->jsonb('data')->default('{}');
            $table->uuid('actor_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->tenantKey();
            $table->index(['academy_id', 'lead_id', 'created_at']);
            $table->tenantForeign('lead_id', 'leads')->cascadeOnDelete();
        });
        TenantTable::enableRls('lead_activities');

        Schema::create('lead_trials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('lead_id');
            $table->uuid('session_id');
            $table->uuid('teacher_id');
            $table->timestampTz('starts_at');
            $table->string('status', 12)->default('booked'); // booked | attended | missed | cancelled
            // The teacher's assessment, once attended.
            $table->string('level', 120)->nullable();
            $table->uuid('recommended_plan_id')->nullable();
            $table->unsignedSmallInteger('recommended_per_week')->nullable();
            $table->text('assessment')->nullable();
            $table->uuid('assessed_by')->nullable();
            $table->timestampTz('assessed_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'session_id']);
            $table->index(['academy_id', 'teacher_id', 'status']);
            $table->tenantForeign('lead_id', 'leads')->cascadeOnDelete();
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('recommended_plan_id', 'plans');
        });
        DB::statement("alter table lead_trials add constraint lead_trials_status check (status in ('booked', 'attended', 'missed', 'cancelled'))");
        TenantTable::enableRls('lead_trials');
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_trials');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
    }
};
