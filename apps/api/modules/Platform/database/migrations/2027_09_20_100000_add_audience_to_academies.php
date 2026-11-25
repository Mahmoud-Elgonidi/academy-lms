<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Whom the academy teaches (docs/architecture/02, "أكاديميات الكبار
 * والمهنيين"): families (every academy so far, as it is today) or adults
 * only (companies and offices training professionals: no guardians, no
 * children). Changed only through SetAcademyAudience.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academies', function (Blueprint $table) {
            $table->string('audience', 10)->default('families');
        });
        DB::statement("alter table academies add constraint academies_audience_check check (audience in ('families', 'adults'))");
    }

    public function down(): void
    {
        Schema::table('academies', fn (Blueprint $table) => $table->dropColumn('audience'));
    }
};
