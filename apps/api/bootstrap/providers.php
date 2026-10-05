<?php

use App\Providers\AppServiceProvider;
use Modules\Identity\Providers\IdentityServiceProvider;
use Modules\Platform\Providers\PlatformServiceProvider;
use Modules\SharedKernel\Providers\SharedKernelServiceProvider;

// Module providers, in dependency order (see docs/architecture/04-design-patterns.md).
return [
    SharedKernelServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
    AppServiceProvider::class,
];
