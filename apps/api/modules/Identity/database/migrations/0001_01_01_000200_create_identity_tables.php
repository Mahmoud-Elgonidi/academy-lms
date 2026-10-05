<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

return new class extends Migration
{
    public function up(): void
    {
        // Users belong to one academy: the same person at two academies has two
        // accounts (see docs/architecture/03-technical-architecture.md, Identity).
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('name');
            $table->citext('email')->nullable();
            $table->string('phone_e164', 20)->nullable();
            $table->citext('username')->nullable();
            $table->string('password')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_managed_minor')->default(false);
            $table->rememberToken();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'email']);
            $table->unique(['academy_id', 'phone_e164']);
            $table->unique(['academy_id', 'username']);
        });

        DB::statement(
            'alter table users add constraint users_has_login_identifier '
            .'check (email is not null or phone_e164 is not null or username is not null)'
        );

        TenantTable::enableRls('users');

        // Fixed system roles for the MVP; a user can hold several (e.g. admin + teacher).
        Schema::create('user_roles', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('user_id');
            $table->string('role', 20);
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['academy_id', 'user_id', 'role']);
            $table->tenantForeign('user_id', 'users')->cascadeOnDelete();
        });

        TenantTable::enableRls('user_roles');

        // Sanctum tokens. No RLS here on purpose: a token is looked up before any
        // tenant is known, and its academy_id is what decides the tenant.
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
    }
};
