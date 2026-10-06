<?php

namespace Modules\Platform\Contracts;

use Modules\SharedKernel\Tenancy\TenantRef;
use Modules\SharedKernel\Tenancy\TenantResolver;

/** Finds the academy a request is for. Implemented with the BYPASSRLS connection. */
interface TenantDirectory extends TenantResolver
{
    /** By {slug}.{platform domain} or a verified custom domain. */
    public function byHost(string $host): ?TenantRef;

    public function bySlug(string $slug): ?TenantRef;
}
