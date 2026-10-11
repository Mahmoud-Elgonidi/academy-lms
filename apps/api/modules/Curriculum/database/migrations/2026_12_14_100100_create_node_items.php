<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * A lesson's practice items (docs/architecture/02, section 18): what the
 * learner practises after the lesson, in the academy's own words, in one of
 * the shapes every game plays (SharedKernel\Games\ItemKind): a question
 * with choices, parts to put in order (a verse's words, a word's letters),
 * or a pair. The games never carry content of their own; a learner's round
 * is drawn from the items of the lessons they are on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('node_id');
            $table->unsignedInteger('ordinal');
            $table->string('kind', 10);                     // choice | order | pair
            $table->string('prompt', 160)->nullable();
            $table->jsonb('data');                          // {options, answer} | {parts} | {left, right}
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'node_id', 'ordinal']);
            $table->tenantForeign('node_id', 'curriculum_nodes')->cascadeOnDelete();
        });
        DB::statement("alter table node_items add constraint node_items_shape check (
            (kind = 'choice' and jsonb_typeof(data->'options') = 'array' and jsonb_typeof(data->'answer') = 'number')
            or (kind = 'order' and jsonb_typeof(data->'parts') = 'array')
            or (kind = 'pair' and jsonb_typeof(data->'left') = 'string' and jsonb_typeof(data->'right') = 'string'))");
        TenantTable::enableRls('node_items');
    }

    public function down(): void
    {
        Schema::dropIfExists('node_items');
    }
};
