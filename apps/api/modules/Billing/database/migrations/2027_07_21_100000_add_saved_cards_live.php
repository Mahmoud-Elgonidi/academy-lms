<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The payer's money screens (the cards page, the course balance) hear when a
 * card is saved or removed, automatic renewal is turned on or off, or a
 * coming charge moves on: the payer's own `payments` signal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create or replace function lms_payer_cards_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'payments:' || coalesce(new.payer_id, old.payer_id));
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger payment_methods_live_changed after insert or update on payment_methods for each row execute function lms_payer_cards_changed()');
        DB::statement('create trigger auto_charges_live_changed after insert or update on auto_charges for each row execute function lms_payer_cards_changed()');

        DB::statement(<<<'SQL'
            create or replace function lms_auto_renew_changed() returns trigger language plpgsql as $$
            begin
                if new.auto_renew is distinct from old.auto_renew then
                    perform pg_notify('lms_live', 'payments:' || new.payer_id);
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger billing_accounts_live_auto_renew after update on billing_accounts for each row execute function lms_auto_renew_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists billing_accounts_live_auto_renew on billing_accounts');
        DB::statement('drop function if exists lms_auto_renew_changed()');
        DB::statement('drop trigger if exists auto_charges_live_changed on auto_charges');
        DB::statement('drop trigger if exists payment_methods_live_changed on payment_methods');
        DB::statement('drop function if exists lms_payer_cards_changed()');
    }
};
