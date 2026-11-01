<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The family starts a course and pays on its own (docs/architecture/02,
 * section 27, J3). billing_settings.self_serve_enrol: whether families may
 * start a course themselves from their account (on at first; the academy
 * turns it off to enrol through the office only), saved only through
 * SaveSelfServeEnrol (self_serve_saved_by: someone chose it, so the demo's
 * catch-up leaves it). payment_checkouts.return_to: where the payer comes
 * back after the gateway's page when the payment began in the portal or the
 * app (null: the academy's own pay page, as before).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->boolean('self_serve_enrol')->default(true);
            $table->uuid('self_serve_saved_by')->nullable();
        });
        Schema::table('payment_checkouts', function (Blueprint $table) {
            $table->string('return_to', 8)->nullable();         // portal | app
        });
        DB::statement("alter table payment_checkouts add constraint payment_checkouts_return_to check (return_to is null or return_to in ('portal', 'app'))");
    }

    public function down(): void
    {
        DB::statement('alter table payment_checkouts drop constraint if exists payment_checkouts_return_to');
        Schema::table('payment_checkouts', fn (Blueprint $table) => $table->dropColumn('return_to'));
        Schema::table('billing_settings', fn (Blueprint $table) => $table->dropColumn(['self_serve_enrol', 'self_serve_saved_by']));
    }
};
