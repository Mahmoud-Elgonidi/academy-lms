<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Each academy's public page (docs/architecture/02, section 24): what it
 * says (one row per academy, set by its owner or admins: Domain\SiteContent,
 * off until they switch it on), and the requests it brings, a free trial
 * for a learner or a teacher asking to teach. The CRM makes a lead of each
 * trial request (lead_id); staff answer the rest here. Requests leave after
 * a year (site:sweep).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->tenant();
            $table->boolean('enabled')->default(false);
            $table->jsonb('content');                       // Domain\SiteContent::normalize()
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->primary('academy_id');
            $table->tenantForeign('updated_by', 'users');
        });
        DB::statement("alter table site_settings add constraint site_settings_shape check (jsonb_typeof(content) = 'object')");
        TenantTable::enableRls('site_settings');

        Schema::create('site_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('kind', 8);                      // trial | teach
            $table->string('name', 120);
            $table->string('phone', 20);                    // E.164
            $table->citext('email')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('timezone', 64)->nullable();     // IANA, as the visitor's browser said
            $table->char('locale', 2)->default('ar');
            $table->uuid('subject_id')->nullable();
            // A trial: for a child (their first name and birth year) or the visitor themself.
            $table->boolean('for_child')->default(false);
            $table->string('child_name', 60)->nullable();
            $table->unsignedSmallInteger('child_birth_year')->nullable();
            // Teaching: the subjects they teach and for how long.
            $table->jsonb('subject_ids')->default('[]');
            $table->unsignedSmallInteger('years_teaching')->nullable();
            $table->text('message')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('status', 10)->default('new');
            $table->string('note', 500)->nullable();        // staff's, never shown to the visitor
            $table->uuid('lead_id')->nullable();            // the CRM's lead made from it (Site\Contracts\SiteApi::linkLead)
            $table->uuid('handled_by')->nullable();
            $table->timestampTz('handled_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'status', 'created_at']);
            $table->index(['academy_id', 'phone', 'kind']);
            $table->tenantForeign('handled_by', 'users');
        });
        DB::statement("alter table site_requests add constraint site_requests_kind check (kind in ('trial', 'teach'))");
        DB::statement("alter table site_requests add constraint site_requests_status check (status in ('new', 'contacted', 'accepted', 'declined'))");
        DB::statement("alter table site_requests add constraint site_requests_phone check (phone ~ '^\\+[1-9][0-9]{6,14}$')");
        DB::statement("alter table site_requests add constraint site_requests_locale check (locale in ('ar', 'en'))");
        DB::statement("alter table site_requests add constraint site_requests_subjects check (jsonb_typeof(subject_ids) = 'array')");
        // A subject removed later leaves the request without it.
        DB::statement('alter table site_requests add constraint site_requests_subject_fk foreign key (academy_id, subject_id) references subjects (academy_id, id) on delete set null (subject_id)');
        TenantTable::enableRls('site_requests');
    }

    public function down(): void
    {
        Schema::dropIfExists('site_requests');
        Schema::dropIfExists('site_settings');
    }
};
