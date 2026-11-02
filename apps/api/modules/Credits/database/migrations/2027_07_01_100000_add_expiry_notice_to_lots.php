<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Paid-for minutes about to lapse (docs/architecture/02, section 27, J5a):
 * ExpireCredits tells once, EXPIRY_NOTICE_DAYS before a purchased lot's date,
 * that its minutes left will lapse (CreditsExpiringSoon; Billing tells the
 * payer). credit_lots.expiry_noticed_for keeps the date it told about, so a
 * lot whose date moved later (a pause, a holiday given back) is told again
 * for its new date, and never twice for one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table credit_lots add column expiry_noticed_for timestamptz null');
    }

    public function down(): void
    {
        DB::statement('alter table credit_lots drop column if exists expiry_noticed_for');
    }
};
