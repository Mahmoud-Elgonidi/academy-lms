<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A room on our own video counts its actual open time into the academy's
 * video minutes when it closes (docs/architecture/02, section 11): metered_at
 * is the close it was last counted up to, so a room the webhook missed, or
 * one that opened again, is counted by the sweep. Rooms that closed before this
 * month are left as they were (the month before was counted by lesson
 * length); this month's are counted by the sweep at their open time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->timestampTz('metered_at')->nullable());
        DB::statement('alter table rooms no force row level security');
        DB::update("update rooms set metered_at = coalesce(finished_at, started_at, created_at) where provider <> 'livekit' or coalesce(finished_at, started_at, created_at) < date_trunc('month', now())");
        DB::statement('alter table rooms force row level security');
    }

    public function down(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('metered_at'));
    }
};
