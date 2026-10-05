<?php

namespace Modules\SharedKernel\Tenancy;

/**
 * Looks academies up by id. Platform implements it (with the BYPASSRLS
 * connection, since no tenant is set yet while resolving one).
 */
interface TenantResolver
{
    public function byId(string $id): ?TenantRef;
}
