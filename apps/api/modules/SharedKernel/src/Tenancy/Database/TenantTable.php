<?php

namespace Modules\SharedKernel\Tenancy\Database;

use Illuminate\Support\Facades\DB;

/**
 * Migration helpers for tenant tables.
 *
 * In a migration:
 *
 *     Schema::create('things', function (Blueprint $table) {
 *         $table->uuid('id')->primary();
 *         $table->tenant();                          // academy_id + FK + index
 *         $table->tenantKey();                       // unique (academy_id, id) for composite FKs
 *         $table->tenantForeign('user_id', 'users'); // (academy_id, user_id) -> users (academy_id, id)
 *     });
 *     TenantTable::enableRls('things');
 */
final class TenantTable
{
    /**
     * Turns on fail-closed row-level security for a table with an academy_id
     * column: rows are visible and writable only when academy_id equals the
     * current tenant, and with no tenant set nothing matches.
     */
    public static function enableRls(string $table, string $column = 'academy_id'): void
    {
        DB::statement("alter table {$table} enable row level security");
        DB::statement("alter table {$table} force row level security");
        DB::statement(
            "create policy tenant_isolation on {$table} "
            ."using ({$column} = app_current_tenant()) "
            ."with check ({$column} = app_current_tenant())"
        );

        if ($column === 'academy_id') {
            DB::statement("alter table {$table} alter column academy_id set default app_current_tenant()");
        }
    }
}
