<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AcademyPanelProvider;
use App\Providers\Filament\TeacherPanelProvider;
use Modules\Billing\Providers\BillingServiceProvider;
use Modules\Catalog\Providers\CatalogServiceProvider;
use Modules\Classroom\Providers\ClassroomServiceProvider;
use Modules\Communication\Providers\CommunicationServiceProvider;
use Modules\Credits\Providers\CreditsServiceProvider;
use Modules\Curriculum\Providers\CurriculumServiceProvider;
use Modules\Enrollment\Providers\EnrollmentServiceProvider;
use Modules\Identity\Providers\IdentityServiceProvider;
use Modules\Learning\Providers\LearningServiceProvider;
use Modules\People\Providers\PeopleServiceProvider;
use Modules\Platform\Providers\PlatformServiceProvider;
use Modules\Scheduling\Providers\SchedulingServiceProvider;
use Modules\SharedKernel\Providers\SharedKernelServiceProvider;

// Module providers, in dependency order (see docs/architecture/04-design-patterns.md).
return [
    SharedKernelServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
    CommunicationServiceProvider::class,
    CatalogServiceProvider::class,
    PeopleServiceProvider::class,
    CurriculumServiceProvider::class,
    CreditsServiceProvider::class,
    EnrollmentServiceProvider::class,
    BillingServiceProvider::class,
    SchedulingServiceProvider::class,
    LearningServiceProvider::class,
    ClassroomServiceProvider::class,
    AppServiceProvider::class,
    AcademyPanelProvider::class,
    TeacherPanelProvider::class,
];
