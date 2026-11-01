<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * After the trial (docs/architecture/02, section 27, J2): what the family
 * reads of the teacher's assessment (strengths, what to work on, the
 * teacher's words to them), when its report went out, why a trial did not
 * take place (the learner or the teacher away, cancelled by the family or
 * the academy), the offer made with
 * it (the personal coupon, its rate and its end as promised, the one
 * reminder) and which trial a trial follows: booked again after a missed
 * one (rebook) or the free second trial with another teacher (second). The
 * academy's settings: the offer (off at first; 5–50%, 24–336 hours) and the
 * second trial (off at first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_trials', function (Blueprint $table) {
            // What the family reads (the level and the recommendation too); `assessment` stays the academy's.
            $table->text('strengths')->nullable();
            $table->text('to_work_on')->nullable();
            $table->text('family_note')->nullable();
            $table->timestampTz('report_sent_at')->nullable();      // crm.trial_report_ready went out (once)
            // The offer made with the report, as promised then.
            $table->uuid('offer_coupon_id')->nullable();
            $table->unsignedSmallInteger('offer_percent_bp')->nullable();
            $table->timestampTz('offer_ends_at')->nullable();
            $table->timestampTz('offer_reminded_at')->nullable();   // crm.trial_offer_ending went out (once)
            // Why it did not take place: missed (student_absent | teacher_absent) or cancelled (family | academy).
            $table->string('outcome', 16)->nullable();
            // A trial booked from another: again after a missed one, or the second trial with another teacher.
            $table->string('kind', 8)->default('first');            // first | rebook | second
            $table->uuid('follows_trial_id')->nullable();

            $table->tenantForeign('offer_coupon_id', 'coupons');
            $table->index(['academy_id', 'offer_ends_at']);
        });
        // The trial it follows going (its lead or lesson deleted) leaves this one standing on its own.
        DB::statement('alter table lead_trials add constraint lead_trials_follows_fk foreign key (academy_id, follows_trial_id) references lead_trials (academy_id, id) on delete set null (follows_trial_id)');
        DB::statement("alter table lead_trials add constraint lead_trials_kind check (kind in ('first', 'rebook', 'second'))");
        DB::statement("alter table lead_trials add constraint lead_trials_outcome check (outcome is null or outcome in ('student_absent', 'teacher_absent', 'family', 'academy'))");
        DB::statement('alter table lead_trials add constraint lead_trials_offer check ((offer_coupon_id is null) = (offer_ends_at is null) and (offer_coupon_id is null) = (offer_percent_bp is null))');
        // One second trial from a trial: the guarantee is used once.
        DB::statement("create unique index lead_trials_one_second on lead_trials (academy_id, follows_trial_id) where kind = 'second'");

        Schema::table('crm_settings', function (Blueprint $table) {
            $table->boolean('trial_offer')->default(false);
            $table->unsignedSmallInteger('trial_offer_percent')->default(10);
            $table->unsignedSmallInteger('trial_offer_hours')->default(72);
            $table->boolean('second_trial')->default(false);
        });
        DB::statement('alter table crm_settings add constraint crm_settings_offer check (trial_offer_percent between 5 and 50 and trial_offer_hours between 24 and 336)');
    }

    public function down(): void
    {
        DB::statement('alter table crm_settings drop constraint crm_settings_offer');
        Schema::table('crm_settings', fn (Blueprint $table) => $table->dropColumn(['trial_offer', 'trial_offer_percent', 'trial_offer_hours', 'second_trial']));
        DB::statement('drop index lead_trials_one_second');
        DB::statement('alter table lead_trials drop constraint lead_trials_offer, drop constraint lead_trials_kind, drop constraint lead_trials_outcome, drop constraint lead_trials_follows_fk');
        Schema::table('lead_trials', function (Blueprint $table) {
            $table->dropIndex(['academy_id', 'offer_ends_at']);
            $table->dropForeign(['academy_id', 'offer_coupon_id']);
            $table->dropColumn(['strengths', 'to_work_on', 'family_note', 'report_sent_at', 'offer_coupon_id', 'offer_percent_bp', 'offer_ends_at',
                'offer_reminded_at', 'outcome', 'kind', 'follows_trial_id']);
        });
    }
};
