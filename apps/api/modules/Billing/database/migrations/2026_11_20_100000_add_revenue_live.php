<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The revenue screen follows the academy's invoices: one issued, paid (in
 * part or in full) or cancelled raises revenue:{academy} (LiveSignals, the
 * academy's revenue channel), so whoever has it open sees the money move.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create or replace function lms_revenue_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'revenue:' || new.academy_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger invoices_revenue_live after insert or update of status, paid_minor on invoices for each row execute function lms_revenue_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists invoices_revenue_live on invoices');
        DB::statement('drop function if exists lms_revenue_changed()');
    }
};
