<?php

namespace Modules\SharedKernel\Tenancy;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\ConnectionEstablished;

/**
 * Keeps the Postgres session setting `app.tenant_id` in step with the current
 * tenant on the application connection. Every RLS policy reads that setting
 * through app_current_tenant(), so an empty value means "no rows".
 *
 * Only the application connection (database.default, role lms_app) is touched;
 * the owner and platform connections are never tenant-scoped.
 */
final class PgTenantSession
{
    private ?string $tenantId = null;

    private bool $writing = false;

    public function __construct(private readonly DatabaseManager $db) {}

    public function apply(?string $tenantId): void
    {
        $this->tenantId = $tenantId;

        $name = $this->connectionName();

        // An unopened connection gets the value from onConnectionEstablished.
        if (array_key_exists($name, $this->db->getConnections())) {
            $this->write($this->db->connection($name));
        }
    }

    /**
     * Re-applies the tenant whenever the app connection is (re)opened. The
     * connection is opened lazily, so with the database unreachable this
     * write is what fails; Laravel then reconnects, which announces the
     * connection again: the write already under way stands for that one
     * (Laravel retries it once), so a database that is down ends in one
     * error, not in reconnecting until memory runs out.
     */
    public function onConnectionEstablished(ConnectionEstablished $event): void
    {
        if ($event->connectionName !== $this->connectionName() || $this->writing) {
            return;
        }
        $this->writing = true;
        try {
            $this->write($event->connection);
        } finally {
            $this->writing = false;
        }
    }

    public function current(): ?string
    {
        return $this->tenantId;
    }

    private function write(Connection $connection): void
    {
        $connection->select("select set_config('app.tenant_id', ?, false)", [$this->tenantId ?? '']);
    }

    private function connectionName(): string
    {
        return config('database.default');
    }
}
