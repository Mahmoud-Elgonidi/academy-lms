<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Signing in with Google or Apple (docs/architecture/02, "الدخول بجوجل وأبل").
 *
 * social_identities: an account's link to a Google or Apple account (the
 * provider's subject, never the email, is what is matched once linked), one
 * per provider per account, and one account per provider subject in an
 * academy. Made at the first sign-in whose verified email is the account's,
 * or by the person from their settings.
 *
 * social_sign_ins: what a provider confirmed, for a few minutes: a hand-off
 * from the platform's callback to the academy's own host (a ticket the
 * browser that started it redeems once), or, for the shared app, a
 * discovery of the person's accounts across academies. Only token hashes are
 * kept, the email encrypted; rows are pruned after a day. No RLS, like
 * otp_challenges: a discovery belongs to no academy, and a row is found by
 * its token's hash, never listed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_identities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('user_id');
            $table->string('provider', 20);                 // google | apple
            $table->string('subject', 255);                  // the provider's stable id for the person
            $table->string('email', 255)->nullable();        // as the provider gave it, to show which account is linked
            $table->string('via', 20);                       // email (first sign-in, same verified email) | settings
            $table->timestampTz('linked_at');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->tenantForeign('user_id', 'users')->cascadeOnDelete();
            $table->unique(['academy_id', 'provider', 'subject']);
            $table->unique(['academy_id', 'user_id', 'provider']);
            $table->index(['provider', 'subject']);           // discovery across academies (platform connection)
        });
        DB::statement("alter table social_identities add constraint social_identities_provider check (provider in ('google', 'apple'))");
        DB::statement("alter table social_identities add constraint social_identities_via check (via in ('email', 'settings'))");
        TenantTable::enableRls('social_identities');

        Schema::create('social_sign_ins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('purpose', 20);                   // handoff | discover
            $table->foreignUuid('academy_id')->nullable()->constrained('academies')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('subject', 255);
            $table->text('email')->nullable();               // encrypted
            $table->boolean('email_verified')->default(false);
            $table->string('intent', 20)->nullable();        // handoff: sign_in | link
            $table->uuid('user_id')->nullable();             // handoff to link: whose settings started it
            $table->string('client', 40)->nullable();        // handoff: panel:{id} | portal
            $table->char('bind_hash', 64)->nullable();       // handoff: only the browser that started it may redeem it
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('created_at');
        });
        DB::statement("alter table social_sign_ins add constraint social_sign_ins_purpose check (purpose in ('handoff', 'discover') and (purpose = 'discover') = (academy_id is null))");
        DB::statement("alter table social_sign_ins add constraint social_sign_ins_handoff check (purpose = 'discover' or (intent in ('sign_in', 'link') and client is not null and bind_hash is not null and (intent = 'sign_in') = (user_id is null)))");
    }

    public function down(): void
    {
        Schema::dropIfExists('social_sign_ins');
        Schema::dropIfExists('social_identities');
    }
};
