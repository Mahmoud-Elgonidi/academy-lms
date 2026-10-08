<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Database\Seeders\DemoBillingSeeder;
use Modules\Billing\Database\Seeders\DemoPaymentsSeeder;
use Modules\Classroom\Database\Seeders\DemoClassroomSeeder;
use Modules\Communication\Database\Seeders\DemoCommunicationSeeder;
use Modules\Crm\Database\Seeders\DemoCrmSeeder;
use Modules\Curriculum\Database\Seeders\DemoCurriculumSeeder;
use Modules\Evaluation\Database\Seeders\DemoEvaluationSeeder;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Database\Seeders\DemoSupportSeeder;
use Modules\Identity\Enums\Role;
use Modules\Import\Database\Seeders\DemoImportSeeder;
use Modules\Learning\Database\Seeders\DemoChallengesSeeder;
use Modules\Learning\Database\Seeders\DemoLearningSeeder;
use Modules\Payroll\Database\Seeders\DemoPayrollSeeder;
use Modules\People\Database\Seeders\DemoConsentsSeeder;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Database\Seeders\DemoPlatformSeeder;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Scheduling\Database\Seeders\DemoDstSeeder;
use Modules\Scheduling\Database\Seeders\DemoLiveBoardSeeder;
use Modules\Scheduling\Database\Seeders\DemoShiftsSeeder;
use Modules\Scheduling\Database\Seeders\DemoTimeOffSeeder;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Local demo data: academy "demo" (demo.<platform domain>, or header X-Academy: demo)
 * with one user per main role, an evening of sessions around now for the
 * live board, guardians' consent to their children's accounts (one still waiting), teacher shifts and proposals, plans and prices, and enrolled
 * students with invoices in every state (payable online on the demo gateway),
 * curricula, lesson reports with homework, teacher time off with a substitute
 * and an academy holiday, ratings, teachers' pay with last month closed, imports from sheets, and the notifications all of that sent;
 * a second academy (suspended) and the platform team's console with past support sign-ins. Every password is "password" (DEMO_PASSWORD on a server). `composer demo` rebuilds it.
 */
class DatabaseSeeder extends Seeder
{
    public function run(PlatformApi $platform, TenantDirectory $directory, IdentityApi $identity, TenantContext $tenants): void
    {
        if ($directory->bySlug('demo') !== null) {
            $this->command?->info('Academy "demo" already exists; nothing to seed.');

            return;
        }

        $password = (string) config('app.demo.password');

        $academy = $platform->createAcademy(new NewAcademy(
            slug: 'demo',
            nameAr: 'أكاديمية النور التجريبية',
            nameEn: 'Noor Demo Academy',
            baseCurrency: 'SAR',
            defaultTimezone: 'Asia/Riyadh',
            defaultLocale: 'ar',
            country: 'SA',
        ));

        $tenants->run($academy, function () use ($identity, $password): void {
            $ownerId = null;
            $people = [
                ['هالة منصور', 'owner@demo.test', [Role::Owner, Role::Admin]],
                ['منسّق الجداول', 'scheduler@demo.test', [Role::Scheduler]],
                ['محاسب الأكاديمية', 'accountant@demo.test', [Role::Accountant]],
                ['ريم عادل', 'sales@demo.test', [Role::Sales]],
                ['أ. أحمد', 'teacher@demo.test', [Role::Teacher]],
                ['ولي أمر', 'guardian@demo.test', [Role::Guardian]],
                ['طالب', 'student@demo.test', [Role::Student]],
            ];

            foreach ($people as [$name, $email, $roles]) {
                $id = $identity->createUser(new NewUser(name: $name, roles: $roles, email: $email, password: $password, locale: 'ar', timezone: 'Africa/Cairo'));
                $ownerId ??= $id;
            }
            // What the demo does in the back office (prices, policy, voided invoices, ...) is in the activity log as the owner's.
            AuditActor::use(new AuditActor('user', $ownerId, 'هالة منصور'));

            $this->call([DemoCommunicationSeeder::class, DemoLiveBoardSeeder::class, DemoConsentsSeeder::class, DemoBillingSeeder::class, DemoShiftsSeeder::class, DemoCurriculumSeeder::class, DemoLearningSeeder::class, DemoChallengesSeeder::class, DemoClassroomSeeder::class, DemoPaymentsSeeder::class, DemoTimeOffSeeder::class, DemoDstSeeder::class, DemoCrmSeeder::class, DemoEvaluationSeeder::class, DemoPayrollSeeder::class, DemoImportSeeder::class]);
            AuditActor::forget();
        });

        // A second, smaller academy for the platform console; the platform team has suspended it.
        $rawda = $platform->createAcademy(new NewAcademy(
            slug: 'rawda',
            nameAr: 'أكاديمية الروضة',
            nameEn: 'Rawda Academy',
            baseCurrency: 'EGP',
            defaultTimezone: 'Africa/Cairo',
            defaultLocale: 'ar',
            country: 'EG',
        ));
        $tenants->run($rawda, function () use ($identity, $password): void {
            $identity->createUser(new NewUser(name: 'محمود سامي', roles: [Role::Owner, Role::Admin], email: 'owner@rawda.test', password: $password, locale: 'ar', timezone: 'Africa/Cairo'));
            foreach (['أ. منى', 'أ. حسن'] as $i => $name) {
                $identity->createUser(new NewUser(name: $name, roles: [Role::Teacher], email: 'teacher'.($i + 1).'@rawda.test', password: $password));
            }
            foreach (['يوسف', 'ليلى', 'آدم', 'سلمى'] as $i => $name) {
                $identity->createUser(new NewUser(name: $name, roles: [Role::Student], email: 'student'.($i + 1).'@rawda.test', password: $password));
            }
        });

        // The platform team: admin@platform.test (the demo password) at /console, with past support sign-ins in the demo academy.
        $this->call(DemoPlatformSeeder::class);
        $admin = PlatformAdmin::query()->where('email', 'admin@platform.test')->firstOrFail();
        $tenants->run($academy, fn () => $this->callWith(DemoSupportSeeder::class, ['adminId' => $admin->id, 'adminName' => $admin->name]));

        $this->command?->info('Seeded academy "demo". Sign in as owner@demo.test with the demo password.');
        $this->command?->info('Platform console: admin@platform.test at /console; the code: php artisan platform:code admin@platform.test');
    }
}
