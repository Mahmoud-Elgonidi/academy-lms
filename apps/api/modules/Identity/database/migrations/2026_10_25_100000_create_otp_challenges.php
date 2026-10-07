<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * One-time codes (docs/architecture/03, "الهوية"). A challenge is a code sent to
 * an email or a phone, to sign in to one academy (sign_in) or to list the
 * person's accounts across academies (discover). The code and the discovery
 * token are kept only as hashes, the contact encrypted (and as a keyed hash to
 * count codes per contact); rows are pruned after a day. No RLS, like personal_access_tokens: a
 * discovery challenge belongs to no academy, and a challenge is found by its
 * random id (or the discovery token's hash), never listed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('purpose', 20);                  // sign_in | discover
            $table->foreignUuid('academy_id')->nullable()->constrained('academies')->cascadeOnDelete();
            $table->uuid('user_id')->nullable();             // sign_in: the account the contact belongs to, if any
            $table->string('channel', 20);                   // whatsapp | sms | email
            $table->char('destination_hash', 64);
            $table->text('destination');                     // encrypted: discovery lists the accounts it belongs to
            $table->char('code_hash', 64);
            $table->smallInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->char('token_hash', 64)->nullable()->unique();   // discover: what lets the app sign in to the accounts found
            $table->string('ip', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['destination_hash', 'created_at']);
            $table->index(['ip', 'created_at']);
            $table->index('created_at');
        });

        DB::statement("alter table otp_challenges add constraint otp_challenges_purpose check (purpose in ('sign_in', 'discover') and (purpose = 'discover') = (academy_id is null))");
        DB::statement("alter table otp_challenges add constraint otp_challenges_channel check (channel in ('whatsapp', 'sms', 'email'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
