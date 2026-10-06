<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Credits in minutes (docs/architecture/02-domain-rules.md, section 3):
 * an append-only ledger, lots that expire, and one reservation per session.
 * credit_accounts holds totals derived from the ledger, kept in step in the
 * same transaction as every entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->string('scope_type', 20);           // enrollment | student_pool
            $table->uuid('scope_id')->nullable();
            $table->integer('available_min')->default(0);
            $table->integer('reserved_min')->default(0);
            $table->integer('consumed_min')->default(0);
            $table->unsignedInteger('low_balance_min')->nullable(); // CreditBalanceLow fires at or under this
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'scope_type', 'scope_id']);
            $table->index(['academy_id', 'student_id']);
            $table->tenantForeign('student_id', 'users');
        });
        DB::statement('alter table credit_accounts add constraint credit_accounts_non_negative check (available_min >= 0 and reserved_min >= 0 and consumed_min >= 0)');
        TenantTable::enableRls('credit_accounts');

        Schema::create('credit_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('account_id');
            $table->string('source', 20);               // purchase | makeup | trial | bonus | adjustment
            $table->uuid('source_id')->nullable();      // the invoice, or the session a makeup is owed for
            $table->unsignedInteger('granted_min');
            $table->unsignedInteger('remaining_min');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('created_at');

            $table->tenantKey();
            $table->index(['academy_id', 'account_id', 'expires_at']);
            $table->tenantForeign('account_id', 'credit_accounts')->cascadeOnDelete();
        });
        DB::statement('alter table credit_lots add constraint credit_lots_remaining check (remaining_min <= granted_min)');
        TenantTable::enableRls('credit_lots');

        Schema::create('credit_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('account_id');
            $table->uuid('lot_id')->nullable();
            $table->uuid('session_id')->nullable();     // a reference, not a key: Credits knows no sessions table
            $table->string('type', 10);                 // grant | reserve | release | consume | expire
            $table->unsignedInteger('minutes');
            $table->integer('available_after');
            $table->integer('reserved_after');
            $table->string('idempotency_key', 160);
            $table->uuid('actor_id')->nullable();
            $table->string('reason', 60)->nullable();
            $table->jsonb('meta')->default('{}');
            $table->timestampTz('occurred_at');

            $table->unique(['academy_id', 'idempotency_key']);
            $table->index(['academy_id', 'account_id', 'occurred_at']);
            $table->tenantForeign('account_id', 'credit_accounts')->cascadeOnDelete();
        });
        TenantTable::enableRls('credit_entries');
        DB::statement('revoke update, delete on credit_entries from lms_app');

        Schema::create('credit_reservations', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('session_id');
            $table->uuid('account_id');
            $table->unsignedInteger('minutes');
            $table->jsonb('allocations');               // [{lot_id, minutes}]
            $table->string('status', 10)->default('held'); // held | consumed | released
            $table->timestampsTz();

            $table->primary(['academy_id', 'session_id']);
            $table->index(['academy_id', 'account_id', 'status']);
            $table->tenantForeign('account_id', 'credit_accounts')->cascadeOnDelete();
        });
        TenantTable::enableRls('credit_reservations');
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_reservations');
        Schema::dropIfExists('credit_entries');
        Schema::dropIfExists('credit_lots');
        Schema::dropIfExists('credit_accounts');
    }
};
