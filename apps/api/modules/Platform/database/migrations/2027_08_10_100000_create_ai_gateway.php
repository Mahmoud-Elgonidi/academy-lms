<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The AI gateway (docs/architecture/02, section 22): one way to a model for
 * the whole system, set from the console by the platform's owners.
 *
 * - ai_engines: each place a model answers (Claude, an OpenAI-compatible
 *   server such as OpenAI, vLLM or Ollama's /v1, Gemini, Ollama, ElevenLabs'
 *   or a Whisper-compatible speech to text, the demo), with its address,
 *   its key (encrypted with APP_KEY, never shown again), its models, its
 *   time-out and what it costs (numbers only, for the reports to come).
 * - ai_routes: for a capability and what it needs (text or speech), the
 *   ordered engines it goes to (the first, then the next on an error, a
 *   time-out or an overload), each with an optional tier of its own, and an
 *   optional share of requests sent to a second chain to compare. Scoped:
 *   default, a plan (plan:{code}) or one academy (academy:{id}); the
 *   narrowest wins, and with none the server's settings hold as before.
 * - ai_test_cases: the inputs the comparison tool runs through two chains;
 *   written by the platform's owners, never a learner's data.
 *
 * Platform connection only (row-level security on with no policy, nothing
 * granted to the academies' role). ai_usage learns which engine answered,
 * how long it took and which engine it fell back from; ai_settings a list of
 * the capabilities beyond the first five that an academy switched off (on
 * at first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_engines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80)->unique();
            $table->string('kind', 30);                          // Platform\Ai\Engines\EngineKind
            $table->string('base_url', 300)->nullable();
            $table->text('api_key')->nullable();                 // encrypted
            $table->string('model_best', 120)->nullable();       // text
            $table->string('model_fast', 120)->nullable();       // text; the best one when empty
            $table->string('model_speech', 120)->nullable();     // speech to text
            $table->smallInteger('timeout_seconds')->nullable(); // else AI_TIMEOUT / AI_JOB_TIMEOUT
            $table->decimal('cost_input_per_m', 12, 4)->nullable();   // per million input tokens
            $table->decimal('cost_output_per_m', 12, 4)->nullable();  // per million output tokens
            $table->decimal('cost_audio_per_min', 12, 4)->nullable(); // per minute of audio heard
            $table->decimal('cost_gpu_per_min', 12, 4)->nullable();   // a server of one's own: per minute it worked
            $table->char('cost_currency', 3)->default('USD');
            $table->json('options')->nullable();                 // per kind: ElevenLabs' zero retention
            $table->boolean('enabled')->default(true);
            $table->text('notes')->nullable();
            $table->uuid('updated_by')->nullable();              // platform_admins.id
            $table->timestampsTz();
        });
        DB::statement("alter table ai_engines add constraint ai_engines_kind check (kind in ('anthropic', 'openai_compatible', 'gemini', 'ollama', 'elevenlabs_stt', 'whisper_compatible', 'demo'))");
        DB::statement('alter table ai_engines add constraint ai_engines_timeout check (timeout_seconds is null or timeout_seconds between 1 and 600)');

        Schema::create('ai_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('capability', 40);
            $table->string('kind', 10);                          // text, speech
            $table->string('scope', 60);                         // default, plan:{code}, academy:{id}
            $table->jsonb('chain');                              // [{engine: uuid, tier: null|fast|best}, …]
            $table->smallInteger('split_percent')->nullable();   // this share of requests goes to split_chain
            $table->jsonb('split_chain')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->unique(['capability', 'kind', 'scope']);
        });
        DB::statement("alter table ai_routes add constraint ai_routes_kind check (kind in ('text', 'speech'))");
        DB::statement("alter table ai_routes add constraint ai_routes_scope check (scope = 'default' or scope ~ '^(plan|academy):.+$')");
        DB::statement('alter table ai_routes add constraint ai_routes_split check ((split_percent is null) = (split_chain is null) and coalesce(split_percent, 1) between 1 and 99)');

        Schema::create('ai_test_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('capability', 40);
            $table->string('kind', 10);
            $table->string('title', 120);
            $table->text('system')->nullable();                  // text: the system prompt
            $table->text('prompt')->nullable();                  // text: the request
            $table->jsonb('schema')->nullable();                 // text: the answer's JSON schema
            $table->string('audio_path', 300)->nullable();       // speech: on the private disk, under platform/ai-tests/
            $table->string('audio_mime', 80)->nullable();
            $table->string('language', 3)->default('ara');
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
        });
        DB::statement("alter table ai_test_cases add constraint ai_test_cases_kind check (kind in ('text', 'speech'))");

        foreach (['ai_engines', 'ai_routes', 'ai_test_cases'] as $table) {
            DB::statement("alter table {$table} enable row level security");
            DB::statement("alter table {$table} force row level security");
            DB::statement("revoke all on {$table} from lms_app");
        }

        Schema::table('ai_usage', function (Blueprint $table) {
            $table->uuid('engine_id')->nullable();               // ai_engines.id; none: the server's settings
            $table->integer('latency_ms')->nullable();
            $table->uuid('fallback_from')->nullable();           // the engine that failed just before this one
        });
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->jsonb('disabled')->default('[]');            // capabilities beyond the first five the academy switched off
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', fn (Blueprint $table) => $table->dropColumn('disabled'));
        Schema::table('ai_usage', fn (Blueprint $table) => $table->dropColumn(['engine_id', 'latency_ms', 'fallback_from']));
        Schema::dropIfExists('ai_test_cases');
        Schema::dropIfExists('ai_routes');
        Schema::dropIfExists('ai_engines');
    }
};
