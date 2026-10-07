<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Two more ways to be paid: Paymob (Egypt) on the academy's own keys, and
 * the academy's own Stripe account connected to the platform (Stripe
 * Connect Standard: no keys typed; the platform acts for the connected
 * account). A connected account's id is kept in the open (it is no secret),
 * so the platform's Connect webhook can find it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table gateway_accounts drop constraint gateway_accounts_gateway');
        DB::statement("alter table gateway_accounts add constraint gateway_accounts_gateway check (gateway in ('stripe', 'stripe_connect', 'moyasar', 'tap', 'paymob', 'demo'))");
        Schema::table('gateway_accounts', function (Blueprint $table) {
            $table->string('external_id', 64)->nullable();   // a connected Stripe account (acct_…)
        });
        DB::statement('create unique index gateway_accounts_external on gateway_accounts (academy_id, gateway, external_id) where external_id is not null');
    }

    public function down(): void
    {
        DB::statement('drop index if exists gateway_accounts_external');
        Schema::table('gateway_accounts', fn (Blueprint $table) => $table->dropColumn('external_id'));
        DB::statement('alter table gateway_accounts drop constraint gateway_accounts_gateway');
        DB::statement("alter table gateway_accounts add constraint gateway_accounts_gateway check (gateway in ('stripe', 'moyasar', 'tap', 'demo'))");
    }
};
