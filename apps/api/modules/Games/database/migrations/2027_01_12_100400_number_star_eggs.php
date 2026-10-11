<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Each egg the journey's stars fill hatches once (docs/architecture/02,
 * section 18): a creature from stars keeps which of the learner's eggs it
 * came from, one creature per egg, held by the database, so a round ending
 * and the map's tap at the same moment hatch one creature, not two on two
 * islands. Creatures already found are numbered in the order they hatched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creature_finds', function (Blueprint $table) {
            $table->smallInteger('egg')->nullable();            // stars: the learner's n-th egg (1, 2, ...); mastery: null
        });

        // The owner sees every academy's rows only with row-level security set aside for a moment.
        DB::statement('alter table creature_finds no force row level security');
        DB::statement(<<<'SQL'
            update creature_finds f set egg = n.egg
            from (select id, row_number() over (partition by academy_id, student_id order by found_at, id) as egg
                  from creature_finds where source = 'stars') n
            where f.id = n.id
        SQL);
        DB::statement('alter table creature_finds force row level security');

        DB::statement("alter table creature_finds add constraint creature_finds_egg check ((source = 'stars') = (egg is not null) and coalesce(egg, 1) > 0)");
        DB::statement('create unique index creature_finds_one_per_egg on creature_finds (academy_id, student_id, egg) where egg is not null');
    }

    public function down(): void
    {
        DB::statement('drop index if exists creature_finds_one_per_egg');
        DB::statement('alter table creature_finds drop constraint if exists creature_finds_egg');
        Schema::table('creature_finds', function (Blueprint $table) {
            $table->dropColumn('egg');
        });
    }
};
