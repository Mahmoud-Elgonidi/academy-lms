<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A code that proves a visitor's mobile number before they book a free
 * trial from the academy's page (the family's journey, docs/architecture/02
 * section 27): purpose "trial", in an academy, with or without an account.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table otp_challenges drop constraint otp_challenges_purpose');
        DB::statement("alter table otp_challenges add constraint otp_challenges_purpose check (purpose in ('sign_in', 'discover', 'contact', 'trial') and (purpose = 'discover') = (academy_id is null))");
    }

    public function down(): void
    {
        DB::statement("delete from otp_challenges where purpose = 'trial'");
        DB::statement('alter table otp_challenges drop constraint otp_challenges_purpose');
        DB::statement("alter table otp_challenges add constraint otp_challenges_purpose check (purpose in ('sign_in', 'discover', 'contact') and (purpose = 'discover') = (academy_id is null))");
    }
};
