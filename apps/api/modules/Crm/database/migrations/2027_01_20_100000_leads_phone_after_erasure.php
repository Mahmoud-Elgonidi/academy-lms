<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * A lead whose learner or contact had their account erased keeps its line in
 * the academy's sales figures without their number (Privacy, LeadsData), so
 * the number may be empty. Every new lead still needs one (LeadFields), and
 * one that is there is still E.164 (leads_phone).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table leads alter column phone_e164 drop not null');
    }

    public function down(): void
    {
        // Refused while an erased lead has no number: none is made up for it.
        DB::statement('alter table leads alter column phone_e164 set not null');
    }
};
