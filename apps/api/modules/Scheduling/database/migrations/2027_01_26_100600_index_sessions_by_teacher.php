<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A teacher's lessons over a stretch of days: their week in the app and the
 * portal, their KPIs and performance events (90 and 30 days), the calendar
 * narrowed to them, and every booking's look at what they already have.
 * Without it each of these reads all of the academy's lessons in the range
 * (docs/architecture/03, section 13: a 400-student academy's 90 days are
 * about 10,000 lessons, for some 250 of one teacher's).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', fn (Blueprint $table) => $table->index(['academy_id', 'teacher_id', 'starts_at']));
    }

    public function down(): void
    {
        Schema::table('sessions', fn (Blueprint $table) => $table->dropIndex(['academy_id', 'teacher_id', 'starts_at']));
    }
};
