<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The platform's own site (docs/architecture/02, section 23): what its page
 * says (one row, set from the console by an owner: Site\SiteContent), which
 * plans it shows and how, and the academies that asked to join from it
 * (platform_signup_requests: the platform team answers each from the
 * console, and makes the academy from it). Only the platform connection reads
 * them (row-level security on with no policy, nothing granted to lms_app).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_site', function (Blueprint $table) {
            $table->smallInteger('id')->primary();
            $table->jsonb('content');                    // Site\SiteContent::normalize()
            $table->uuid('updated_by')->nullable();      // platform_admins.id
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('alter table platform_site add constraint platform_site_one check (id = 1)');
        DB::statement("alter table platform_site add constraint platform_site_shape check (jsonb_typeof(content) = 'object')");

        Schema::table('saas_plans', function (Blueprint $table) {
            $table->boolean('shown_on_site')->default(true);   // on the platform's page while offered
            $table->boolean('highlight')->default(false);      // the one the page sets apart
            $table->string('tagline_ar', 120)->nullable();
            $table->string('tagline_en', 120)->nullable();
        });
        DB::statement('create unique index saas_plans_one_highlight on saas_plans ((true)) where highlight and archived_at is null');

        Schema::create('platform_signup_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('academy_name', 120);
            $table->string('contact_name', 120);
            $table->citext('email');
            $table->string('phone', 20)->nullable();           // E.164
            $table->char('country', 2)->nullable();
            $table->string('students', 10)->nullable();        // Site\SignupRequests::STUDENTS
            $table->citext('plan_code')->nullable();           // the plan they looked at, as it was named then
            $table->text('message')->nullable();
            $table->char('locale', 2)->default('ar');
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('status', 12)->default('new');
            $table->text('note')->nullable();                  // the platform team's, never shown to them
            $table->uuid('academy_id')->nullable();            // the academy made from it
            $table->uuid('handled_by')->nullable();            // platform_admins.id
            $table->timestampTz('handled_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
            $table->index('email');
        });
        DB::statement("alter table platform_signup_requests add constraint platform_signup_requests_status check (status in ('new', 'contacted', 'created', 'declined'))");

        foreach (['platform_site', 'platform_signup_requests'] as $name) {
            DB::statement("alter table {$name} enable row level security");
            DB::statement("alter table {$name} force row level security");
            DB::statement("revoke all on {$name} from lms_app");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_signup_requests');
        DB::statement('drop index if exists saas_plans_one_highlight');
        Schema::table('saas_plans', fn (Blueprint $table) => $table->dropColumn(['shown_on_site', 'highlight', 'tagline_ar', 'tagline_en']));
        Schema::dropIfExists('platform_site');
    }
};
