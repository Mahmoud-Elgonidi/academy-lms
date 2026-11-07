<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The free plan («البداية») holds 600 video minutes a month instead of 3,000.
 * Only a starter plan still on the first figure changes: one the platform
 * team already set by hand in the console keeps its own number, and so does
 * an academy with its own limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->set(3000, 600);
    }

    public function down(): void
    {
        $this->set(600, 3000);
    }

    private function set(int $from, int $to): void
    {
        DB::statement('alter table saas_plans no force row level security');
        DB::update("update saas_plans set video_minutes = ?, updated_at = now() where code = 'starter' and video_minutes = ?", [$to, $from]);
        DB::statement('alter table saas_plans force row level security');
    }
};
