<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AcademyPanelProvider;
use App\Providers\Filament\ConsolePanelProvider;
use App\Providers\Filament\FamilyPanelProvider;
use App\Providers\Filament\TeacherPanelProvider;
use Modules\Announcements\Providers\AnnouncementsServiceProvider;
use Modules\Billing\Providers\BillingServiceProvider;
use Modules\Catalog\Providers\CatalogServiceProvider;
use Modules\Classroom\Providers\ClassroomServiceProvider;
use Modules\Communication\Providers\CommunicationServiceProvider;
use Modules\Credits\Providers\CreditsServiceProvider;
use Modules\Crm\Providers\CrmServiceProvider;
use Modules\Curriculum\Providers\CurriculumServiceProvider;
use Modules\Enrollment\Providers\EnrollmentServiceProvider;
use Modules\Evaluation\Providers\EvaluationServiceProvider;
use Modules\Games\Providers\GamesServiceProvider;
use Modules\Identity\Providers\IdentityServiceProvider;
use Modules\Import\Providers\ImportServiceProvider;
use Modules\Learning\Providers\LearningServiceProvider;
use Modules\Messaging\Providers\MessagingServiceProvider;
use Modules\Payroll\Providers\PayrollServiceProvider;
use Modules\People\Providers\PeopleServiceProvider;
use Modules\Platform\Providers\PlatformServiceProvider;
use Modules\Privacy\Providers\PrivacyServiceProvider;
use Modules\Safeguarding\Providers\SafeguardingServiceProvider;
use Modules\Scheduling\Providers\SchedulingServiceProvider;
use Modules\SharedKernel\Providers\SharedKernelServiceProvider;
use Modules\Site\Providers\SiteServiceProvider;
use Modules\Support\Providers\SupportServiceProvider;

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
    EvaluationServiceProvider::class,
    PayrollServiceProvider::class,
    ClassroomServiceProvider::class,
    SiteServiceProvider::class,
    CrmServiceProvider::class,
    ImportServiceProvider::class,
    SupportServiceProvider::class,
    PrivacyServiceProvider::class,
    SafeguardingServiceProvider::class,
    MessagingServiceProvider::class,
    GamesServiceProvider::class,
    AnnouncementsServiceProvider::class,
    AppServiceProvider::class,
    AcademyPanelProvider::class,
    TeacherPanelProvider::class,
    FamilyPanelProvider::class,
    ConsolePanelProvider::class,
];
