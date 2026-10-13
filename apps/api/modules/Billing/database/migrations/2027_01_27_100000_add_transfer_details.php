<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The academy's transfer details (docs/architecture/02, section 3): where a
 * family sends the money by hand (a bank, the account holder and the IBAN,
 * an InstaPay, Vodafone Cash or STC Pay number), as plain text of several
 * lines. billing_settings.manual_payment_note is the academy's own text, for
 * every payer; a price region may have its own, which the payers in it read
 * instead. Both are written only through SaveTransferDetails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_regions', function (Blueprint $table) {
            $table->text('transfer_details')->nullable();
        });
        DB::statement('alter table price_regions add constraint price_regions_transfer_details_length check (char_length(transfer_details) <= 1000)');
        // The screen always kept it to 1,000 characters; rows already there are not checked again.
        DB::statement('alter table billing_settings add constraint billing_settings_manual_note_length check (char_length(manual_payment_note) <= 1000) not valid');
    }

    public function down(): void
    {
        DB::statement('alter table billing_settings drop constraint if exists billing_settings_manual_note_length');
        Schema::table('price_regions', fn (Blueprint $table) => $table->dropColumn('transfer_details'));
    }
};
