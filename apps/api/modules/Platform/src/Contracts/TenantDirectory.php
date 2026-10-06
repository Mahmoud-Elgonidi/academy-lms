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

    /**
     * Where the academy lives on the web, for links that leave the app (a
     * message's "join" link): {slug}.{platform domain} with APP_URL's scheme
     * and port, or APP_URL itself for the local development academy.
     */
    public function baseUrl(TenantRef $tenant): string;
}
