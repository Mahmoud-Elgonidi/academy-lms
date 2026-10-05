<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;

/**
 * Feature tests run against real Postgres with the production roles, so RLS
 * is exercised for real: the app connects as lms_app, migrations and
 * truncation run as lms_owner, platform code uses lms_platform.
 *
 * Truncation instead of a wrapping transaction: data written through the
 * platform connection must be visible to the app connection.
 */
abstract class TestCase extends BaseTestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $connectionsToTruncate = ['pgsql_owner'];

    /** @var list<string> */
    protected array $exceptTables = ['migrations'];

    protected function migrateFreshUsing(): array
    {
        return ['--database' => 'pgsql_owner', '--drop-views' => true, '--seed' => false];
    }

    /**
     * One TRUNCATE over every table, as the owner. Laravel's default skips
     * tables that look empty, and with FORCE RLS every tenant table looks
     * empty to the owner; TRUNCATE itself is not subject to RLS.
     */
    protected function truncateTablesForAllConnections(): void
    {
        $owner = $this->app['db']->connection('pgsql_owner');

        $tables = collect($owner->getSchemaBuilder()->getTableListing('public', false))
            ->reject(fn (string $table) => in_array($table, $this->exceptTables, true))
            ->map(fn (string $table) => '"'.$table.'"');

        if ($tables->isNotEmpty()) {
            $owner->statement('truncate table '.$tables->implode(', ').' restart identity cascade');
        }
    }

    /** Creates an academy through the platform connection. */
    protected function createAcademy(array $attributes = []): TenantRef
    {
        return Academy::factory()->create($attributes)->toTenantRef();
    }

    protected function actAsTenant(TenantRef $tenant): TenantRef
    {
        $this->app->make(TenantContext::class)->set($tenant);

        return $tenant;
    }

    protected function clearTenant(): void
    {
        $this->app->make(TenantContext::class)->clear();
    }
}
