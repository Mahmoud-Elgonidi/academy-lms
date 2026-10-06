<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The bell updates when a message arrives or is read, not on a timer: every
 * write to someone's inbox raises "inbox:{user}" on the live channel
 * (Modules\SharedKernel\Realtime\LiveSignals), sent on commit.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create or replace function lms_inbox_changed() returns trigger language plpgsql as $$
            begin
                if tg_op = 'DELETE' then
                    perform pg_notify('lms_live', 'inbox:' || old.user_id);
                else
                    perform pg_notify('lms_live', 'inbox:' || new.user_id);
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger notifications_live_changed after insert or update or delete on notifications for each row execute function lms_inbox_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists notifications_live_changed on notifications');
        DB::statement('drop function if exists lms_inbox_changed()');
    }
};
