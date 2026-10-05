<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // All four are trusted extensions, so the database owner can create them.
        foreach (['btree_gist', 'ltree', 'pg_trgm', 'citext'] as $extension) {
            DB::statement("create extension if not exists {$extension}");
        }

        // Every RLS policy compares against this. Empty or unset means NULL,
        // and NULL equals nothing, so a session with no tenant sees no rows.
        DB::statement(<<<'SQL'
            create or replace function app_current_tenant() returns uuid
            language sql stable
            as $$ select nullif(current_setting('app.tenant_id', true), '')::uuid $$
        SQL);

        // Platform-level tables used before any tenant is known: no academy_id, no RLS.
        Schema::create('sessions', function ($table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        DB::statement('drop function if exists app_current_tenant()');
    }
};
