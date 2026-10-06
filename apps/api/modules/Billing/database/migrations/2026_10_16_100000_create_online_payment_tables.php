<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Online payments (docs/architecture/03, section 5): the academy is the
 * merchant, on its own gateway accounts (Stripe, Moyasar, Tap). Every
 * invoice can be paid on the gateway's hosted page; the money is recorded
 * only once the gateway itself says it is paid, once (a webhook inbox and
 * one payment per checkout).
 */
return new class extends Migration
{
    public function up(): void
    {
        // The academy's accounts at the gateways; the keys are encrypted with the app key.
        Schema::create('gateway_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('gateway', 20);                  // stripe | moyasar | tap | demo
            $table->string('label', 80);
            $table->string('mode', 4)->default('test');     // test | live
            $table->jsonb('currencies')->default('[]');     // the currencies it takes here; empty: every one the gateway takes
            $table->text('credentials');                    // encrypted JSON
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
        });
        DB::statement("alter table gateway_accounts add constraint gateway_accounts_gateway check (gateway in ('stripe', 'moyasar', 'tap', 'demo'))");
        DB::statement("alter table gateway_accounts add constraint gateway_accounts_mode check (mode in ('test', 'live'))");
        TenantTable::enableRls('gateway_accounts');

        // One go at paying an invoice on a gateway's page.
        Schema::create('payment_checkouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('invoice_id');
            $table->uuid('gateway_account_id');
            $table->uuid('payer_id')->nullable();           // who opened it: the payer signed in, or from their link
            $table->string('channel', 10);                  // family | link
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 12)->default('open');  // open | paid | failed | expired | refund_due
            $table->string('provider_ref', 120)->nullable();
            $table->string('provider_payment_ref', 120)->nullable();
            $table->text('url')->nullable();
            $table->string('failure', 200)->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'invoice_id']);
            $table->index(['academy_id', 'status', 'expires_at']);
            $table->tenantForeign('invoice_id', 'invoices');
            $table->tenantForeign('gateway_account_id', 'gateway_accounts');
        });
        DB::statement('create unique index payment_checkouts_ref on payment_checkouts (academy_id, gateway_account_id, provider_ref) where provider_ref is not null');
        DB::statement("alter table payment_checkouts add constraint payment_checkouts_status check (status in ('open', 'paid', 'failed', 'expired', 'refund_due'))");
        DB::statement('alter table payment_checkouts add constraint payment_checkouts_positive check (amount_minor > 0)');
        TenantTable::enableRls('payment_checkouts');

        // What the gateways told us, each event once.
        Schema::create('gateway_webhook_inbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('gateway_account_id');
            $table->string('event_id', 190);
            $table->string('event', 80);
            $table->string('provider_ref', 120)->nullable();
            $table->jsonb('payload');
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->string('error', 500)->nullable();

            $table->unique(['academy_id', 'gateway_account_id', 'event_id']);
            $table->tenantForeign('gateway_account_id', 'gateway_accounts');
        });
        TenantTable::enableRls('gateway_webhook_inbox');

        // A payment made online comes from one checkout, and one checkout pays once.
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('checkout_id')->nullable();
        });
        DB::statement('create unique index payments_checkout on payments (academy_id, checkout_id) where checkout_id is not null');

        // How to pay by hand (bank transfer, InstaPay…), shown with the invoice when paying online is not offered.
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->text('manual_payment_note')->nullable();
        });

        // The payer's screen hears when their payment goes through.
        DB::statement(<<<'SQL'
            create or replace function lms_checkout_changed() returns trigger language plpgsql as $$
            begin
                if new.payer_id is not null and (tg_op = 'INSERT' or new.status is distinct from old.status) then
                    perform pg_notify('lms_live', 'payments:' || new.payer_id);
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger payment_checkouts_live_changed after insert or update on payment_checkouts for each row execute function lms_checkout_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists payment_checkouts_live_changed on payment_checkouts');
        DB::statement('drop function if exists lms_checkout_changed()');
        Schema::table('billing_settings', fn (Blueprint $table) => $table->dropColumn('manual_payment_note'));
        DB::statement('drop index if exists payments_checkout');
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('checkout_id'));
        Schema::dropIfExists('gateway_webhook_inbox');
        Schema::dropIfExists('payment_checkouts');
        Schema::dropIfExists('gateway_accounts');
    }
};
