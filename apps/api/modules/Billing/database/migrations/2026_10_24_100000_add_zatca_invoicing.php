<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Saudi e-invoicing, phase 1 (docs/architecture/03, "ZATCA"): an academy
 * registered for VAT issues simplified tax invoices carrying the seller's
 * name and VAT number and a QR code (TLV, base64). What an invoice says
 * about its seller is kept on the invoice as issued, so changing the
 * settings later never rewrites an old one. An issued tax invoice is
 * cancelled only by a credit note, which never changes either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->string('seller_name', 120)->nullable();     // the name registered with ZATCA
            $table->boolean('zatca')->default(false);           // simplified tax invoices with the QR
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('seller_name', 120)->nullable();
            $table->string('seller_tax_number', 30)->nullable();
            $table->string('tax_document', 20)->nullable();     // simplified (ZATCA) | null
            $table->text('zatca_qr')->nullable();               // TLV, base64
            $table->uuid('credit_note_id')->nullable();
        });

        Schema::create('credit_note_sequences', function (Blueprint $table) {
            $table->tenant();
            $table->unsignedBigInteger('next_no')->default(1);

            $table->primary('academy_id');
        });
        TenantTable::enableRls('credit_note_sequences');

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('number', 30);
            $table->uuid('invoice_id');
            $table->string('reason', 200);
            $table->char('currency', 3);
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('tax_minor');
            $table->bigInteger('total_minor');
            $table->unsignedInteger('tax_rate_bp');
            $table->string('seller_name', 120)->nullable();
            $table->string('seller_tax_number', 30)->nullable();
            $table->text('zatca_qr')->nullable();
            $table->timestampTz('issued_at');
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'number']);
            $table->unique(['academy_id', 'invoice_id']);       // a full cancellation, once
            $table->tenantForeign('invoice_id', 'invoices');
        });
        DB::statement('alter table credit_notes add constraint credit_notes_amounts check (total_minor >= 0 and tax_minor >= 0 and subtotal_minor >= 0)');
        TenantTable::enableRls('credit_notes');
        DB::unprepared(<<<'SQL'
            create or replace function lms_credit_notes_frozen() returns trigger language plpgsql as $$
            begin
                raise exception 'credit_notes never change once issued' using errcode = 'P0001';
            end $$;
            create trigger credit_notes_frozen before update or delete on credit_notes
                for each row execute function lms_credit_notes_frozen();
            SQL);

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign(['academy_id', 'credit_note_id'])->references(['academy_id', 'id'])->on('credit_notes');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['academy_id', 'credit_note_id']);
            $table->dropColumn(['seller_name', 'seller_tax_number', 'tax_document', 'zatca_qr', 'credit_note_id']);
        });
        DB::statement('drop trigger if exists credit_notes_frozen on credit_notes');
        DB::statement('drop function if exists lms_credit_notes_frozen()');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('credit_note_sequences');
        Schema::table('billing_settings', fn (Blueprint $table) => $table->dropColumn(['seller_name', 'zatca']));
    }
};
