<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A code for a new email or mobile number (the person proves the new
 * address is theirs before it becomes a way in): purpose "contact", in an
 * academy, like a sign-in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table otp_challenges drop constraint otp_challenges_purpose');
        DB::statement("alter table otp_challenges add constraint otp_challenges_purpose check (purpose in ('sign_in', 'discover', 'contact') and (purpose = 'discover') = (academy_id is null))");
    }

    public function down(): void
    {
        DB::statement("delete from otp_challenges where purpose = 'contact'");
        DB::statement('alter table otp_challenges drop constraint otp_challenges_purpose');
        DB::statement("alter table otp_challenges add constraint otp_challenges_purpose check (purpose in ('sign_in', 'discover') and (purpose = 'discover') = (academy_id is null))");
    }
};
