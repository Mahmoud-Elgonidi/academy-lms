<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The live board updates when something on it changes, not on a timer.
 * Every write to a lesson, its participants or its log tells Postgres
 * "academy X's board changed" (pg_notify). Postgres delivers it only when
 * the transaction commits, and folds repeats inside one transaction into a
 * single message, so a whole cancel or join is one signal however many rows
 * it touched. `scheduling:live` listens and passes it on to the browsers.
 */
return new class extends Migration
{
    private const TABLES = ['sessions', 'session_participants', 'session_events'];

    public function up(): void
    {
        DB::statement(<<<'SQL'
            create or replace function lms_board_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_board', (case when tg_op = 'DELETE' then old.academy_id else new.academy_id end)::text);
                return null;
            end
            $$
        SQL);

        foreach (self::TABLES as $table) {
            DB::statement("create trigger {$table}_board_changed after insert or update or delete on {$table} for each row execute function lms_board_changed()");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("drop trigger if exists {$table}_board_changed on {$table}");
        }
        DB::statement('drop function if exists lms_board_changed()');
    }
};
