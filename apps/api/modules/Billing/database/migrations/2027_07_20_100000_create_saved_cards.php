<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * A saved card and automatic renewal (docs/architecture/02, section 27, J5b).
 *
 * payment_methods: a payer's card at one of the academy's gateway accounts, as
 * the gateway's own reference to it (Moyasar's token, Tap's card and customer,
 * Stripe's customer and payment method; the demo gateway's pretend token),
 * encrypted with the app key and never shown, logged or exported; what may be
 * shown is the brand, the last four digits and the expiry. One live card per
 * payer and gateway account (a newer one replaces it). A revoked card keeps
 * its row (brand, last four, dates) with its tokens dropped.
 *
 * billing_accounts.auto_renew: the payer's choice to have renewals charged to
 * the saved card (on when they ticked the mandate at checkout; off in one tap).
 *
 * payment_checkouts gains its kind (page: the gateway's hosted page; saved: a
 * charge on a saved card, no page), whether the payer asked to save the card
 * (with the mandate's version they ticked) and the card it charged.
 *
 * auto_charges: one per invoice, the automatic charge's whole course: noticed
 * three days ahead, charged once (one retry after a soft decline), paid,
 * failed, cancelled (turned off, paid another way, voided, paused) or skipped
 * (no card for its currency, the card expires first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('payer_id');
            $table->uuid('gateway_account_id');
            $table->string('gateway', 20);
            $table->text('tokens');                             // encrypted JSON: the gateway's own references, never the card
            $table->string('brand', 20);
            $table->char('last4', 4);
            $table->unsignedSmallInteger('exp_month');
            $table->unsignedSmallInteger('exp_year');
            $table->string('mandate_version', 20);
            $table->char('mandate_hash', 64);                   // sha256 of the mandate text the payer ticked, in their language
            $table->uuid('checkout_id')->nullable();            // the payment it was saved with
            $table->timestampTz('expiry_noticed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revoked_reason', 20)->nullable();   // payer | replaced | gateway_off | erased | bank (the bank says it is no longer good)
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'payer_id']);
            $table->tenantForeign('payer_id', 'users');
            $table->tenantForeign('gateway_account_id', 'gateway_accounts');
        });
        DB::statement('create unique index payment_methods_live on payment_methods (academy_id, payer_id, gateway_account_id) where revoked_at is null');
        DB::statement('alter table payment_methods add constraint payment_methods_last4 check (last4 ~ \'^[0-9]{4}$\')');
        DB::statement('alter table payment_methods add constraint payment_methods_expiry check (exp_month between 1 and 12 and exp_year between 2000 and 2100)');
        DB::statement("alter table payment_methods add constraint payment_methods_revoked check (revoked_reason is null or revoked_reason in ('payer', 'replaced', 'gateway_off', 'erased', 'bank'))");
        TenantTable::enableRls('payment_methods');

        Schema::table('billing_accounts', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(false);
            $table->timestampTz('auto_renew_changed_at')->nullable();
        });

        Schema::table('payment_checkouts', function (Blueprint $table) {
            $table->string('kind', 6)->default('page');
            $table->boolean('save_card')->default(false);
            $table->string('mandate_version', 20)->nullable();
            $table->uuid('payment_method_id')->nullable();
        });
        DB::statement("alter table payment_checkouts add constraint payment_checkouts_kind check (kind in ('page', 'saved'))");
        DB::statement('alter table payment_checkouts add constraint payment_checkouts_mandate check (not save_card or mandate_version is not null)');

        Schema::create('auto_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('invoice_id');
            $table->uuid('payer_id');
            $table->uuid('payment_method_id')->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 10);                       // scheduled | charging | retry | paid | failed | cancelled | skipped
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('noticed_at')->nullable();
            $table->timestampTz('charge_at')->nullable();
            $table->timestampTz('next_attempt_at')->nullable();
            $table->uuid('checkout_id')->nullable();
            $table->string('failure', 30)->nullable();          // a CardFailure, or why it was skipped
            $table->string('cancel_reason', 20)->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'invoice_id']);
            $table->index(['academy_id', 'status', 'charge_at']);
            $table->index(['academy_id', 'payer_id']);
            $table->tenantForeign('invoice_id', 'invoices');
            $table->tenantForeign('payer_id', 'users');
            $table->tenantForeign('payment_method_id', 'payment_methods');
        });
        DB::statement("alter table auto_charges add constraint auto_charges_status check (status in ('scheduled', 'charging', 'retry', 'paid', 'failed', 'cancelled', 'skipped'))");
        DB::statement('alter table auto_charges add constraint auto_charges_positive check (amount_minor > 0)');
        TenantTable::enableRls('auto_charges');
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_charges');
        DB::statement('alter table payment_checkouts drop constraint if exists payment_checkouts_mandate');
        DB::statement('alter table payment_checkouts drop constraint if exists payment_checkouts_kind');
        Schema::table('payment_checkouts', fn (Blueprint $table) => $table->dropColumn(['kind', 'save_card', 'mandate_version', 'payment_method_id']));
        Schema::table('billing_accounts', fn (Blueprint $table) => $table->dropColumn(['auto_renew', 'auto_renew_changed_at']));
        Schema::dropIfExists('payment_methods');
    }
};
