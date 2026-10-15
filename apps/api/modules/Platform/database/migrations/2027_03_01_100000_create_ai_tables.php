<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/**
 * The academies' AI helpers (docs/architecture/02, "المساعد الآلي"): which
 * helpers each academy turned on (all off until it does), and one line per
 * request a helper made to a provider, for cost and the daily cap. The
 * lines say nothing of who asked or what was said, and are never changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->tenant();
            $table->boolean('practice_items')->default(false);
            $table->boolean('report_draft')->default(false);
            $table->boolean('recitation_check')->default(false);
            $table->boolean('digest_wording')->default(false);
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        TenantTable::enableRls('ai_settings');

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('feature', 40);
            $table->string('provider', 20);
            $table->string('model', 80);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('audio_seconds')->default(0);
            $table->string('outcome', 20);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['academy_id', 'created_at']);
        });
        TenantTable::enableRls('ai_usage');
        DB::statement('revoke update, delete on ai_usage from lms_app');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_settings');
    }
};
