<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A subject's kind (docs/architecture/02, section 26): general, or quran with
 * its track (hifz: memorization, tilawa: recitation and tajweed, talqin:
 * dictation for children, noorani: the Noorani Qaida). A Quran subject's
 * courses are the Quran module's to follow; changed only through SaveSubject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('kind', 10)->default('general');
            $table->string('quran_track', 10)->nullable();
        });
        DB::statement("alter table subjects add constraint subjects_kind check (
            (kind = 'general' and quran_track is null)
            or (kind = 'quran' and quran_track in ('hifz', 'tilawa', 'talqin', 'noorani')))");
    }

    public function down(): void
    {
        DB::statement('alter table subjects drop constraint if exists subjects_kind');
        Schema::table('subjects', fn (Blueprint $table) => $table->dropColumn(['kind', 'quran_track']));
    }
};
