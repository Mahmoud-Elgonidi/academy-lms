<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Paying by hand with a proof (docs/architecture/03, section 5, "يدوي"):
 * the payer transfers (a bank, InstaPay, a wallet), sends the receipt with
 * the amount, and the academy approves it, which records the payment
 * (RecordPayment), or turns it down with a reason. One proof waits per
 * invoice at a time; the file stays on the private disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_proofs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('invoice_id');
            $table->uuid('payer_id');                           // who sent it: the invoice's payer
            $table->string('channel', 10);                      // family (signed in: panel, app, portal) | link (the invoice's pay link)
            $table->string('method', 20);                       // bank_transfer | instapay | wallet | other
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->date('paid_on');                            // the day the payer says they paid, as they wrote it
            $table->string('reference', 120)->nullable();       // the transfer's number
            $table->string('note', 500)->nullable();
            $table->string('file_path');
            $table->string('file_name', 190);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('status', 10)->default('pending');   // pending | approved | rejected
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->string('decision_note', 300)->nullable();   // why it was turned down (the payer reads it)
            $table->uuid('payment_id')->nullable();             // what approving it recorded
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'invoice_id']);
            $table->index(['academy_id', 'status', 'created_at']);
            $table->tenantForeign('invoice_id', 'invoices');
            $table->tenantForeign('payer_id', 'users');
            $table->foreign('payment_id')->references('id')->on('payments');   // payments has no tenant key; RLS keeps it in the academy
        });
        DB::statement("alter table manual_payment_proofs add constraint manual_payment_proofs_status check (status in ('pending', 'approved', 'rejected'))");
        DB::statement("alter table manual_payment_proofs add constraint manual_payment_proofs_method check (method in ('bank_transfer', 'instapay', 'wallet', 'other'))");
        DB::statement("alter table manual_payment_proofs add constraint manual_payment_proofs_channel check (channel in ('family', 'link'))");
        DB::statement('alter table manual_payment_proofs add constraint manual_payment_proofs_positive check (amount_minor > 0)');
        DB::statement("alter table manual_payment_proofs add constraint manual_payment_proofs_decided check ((status = 'pending') = (decided_at is null) and (status = 'approved') = (payment_id is not null))");
        // One proof waits per invoice: the academy reviews it before another comes.
        DB::statement("create unique index manual_payment_proofs_one_pending on manual_payment_proofs (academy_id, invoice_id) where status = 'pending'");
        TenantTable::enableRls('manual_payment_proofs');
        DB::statement('revoke delete on manual_payment_proofs from lms_app');

        // The payer's screens hear when their proof is sent or decided, and when anything is paid on their invoices
        // (a proof approved, a payment recorded in the back office), not only when a gateway confirms.
        DB::statement(<<<'SQL'
            create or replace function lms_payment_proof_changed() returns trigger language plpgsql as $$
            begin
                if tg_op = 'INSERT' or new.status is distinct from old.status then
                    perform pg_notify('lms_live', 'payments:' || new.payer_id);
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger manual_payment_proofs_live_changed after insert or update on manual_payment_proofs for each row execute function lms_payment_proof_changed()');

        DB::statement(<<<'SQL'
            create or replace function lms_invoice_paid_changed() returns trigger language plpgsql as $$
            declare
                payer uuid;
            begin
                if new.paid_minor is distinct from old.paid_minor or new.status is distinct from old.status then
                    select ba.payer_id into payer from billing_accounts ba where ba.academy_id = new.academy_id and ba.id = new.billing_account_id;
                    if payer is not null then
                        perform pg_notify('lms_live', 'payments:' || payer);
                    end if;
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger invoices_live_paid after update on invoices for each row execute function lms_invoice_paid_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists invoices_live_paid on invoices');
        DB::statement('drop function if exists lms_invoice_paid_changed()');
        DB::statement('drop trigger if exists manual_payment_proofs_live_changed on manual_payment_proofs');
        DB::statement('drop function if exists lms_payment_proof_changed()');
        Schema::dropIfExists('manual_payment_proofs');
    }
};
