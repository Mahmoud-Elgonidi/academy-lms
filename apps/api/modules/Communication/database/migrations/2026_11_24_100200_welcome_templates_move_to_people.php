<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The welcome with each person's link to choose a password is People's
     * now (an academy adds people by hand too, not only from a sheet): an
     * academy's own wording and the messages already sent follow the key.
     */
    public function up(): void
    {
        foreach (['welcome_teacher', 'welcome_family'] as $name) {
            foreach (['notification_templates', 'notifications'] as $table) {
                DB::table($table)->where('template_key', "import.{$name}")->update(['template_key' => "people.{$name}"]);
            }
        }
    }

    public function down(): void
    {
        foreach (['welcome_teacher', 'welcome_family'] as $name) {
            foreach (['notification_templates', 'notifications'] as $table) {
                DB::table($table)->where('template_key', "people.{$name}")->update(['template_key' => "import.{$name}"]);
            }
        }
    }
};
