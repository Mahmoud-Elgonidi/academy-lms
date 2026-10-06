<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AcademyPanelProvider;
use Modules\Billing\Providers\BillingServiceProvider;
use Modules\Catalog\Providers\CatalogServiceProvider;
use Modules\Credits\Providers\CreditsServiceProvider;
use Modules\Enrollment\Providers\EnrollmentServiceProvider;
use Modules\Identity\Providers\IdentityServiceProvider;
use Modules\People\Providers\PeopleServiceProvider;
use Modules\Platform\Providers\PlatformServiceProvider;
use Modules\Scheduling\Providers\SchedulingServiceProvider;
use Modules\SharedKernel\Providers\SharedKernelServiceProvider;

// Module providers, in dependency order (see docs/architecture/04-design-patterns.md).
return [
    SharedKernelServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
    CatalogServiceProvider::class,
    PeopleServiceProvider::class,
    CreditsServiceProvider::class,
    EnrollmentServiceProvider::class,
    BillingServiceProvider::class,
    SchedulingServiceProvider::class,
    AppServiceProvider::class,
    AcademyPanelProvider::class,
];
