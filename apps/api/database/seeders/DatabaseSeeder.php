<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Database\Seeders\DemoBillingSeeder;
use Modules\Billing\Database\Seeders\DemoPaymentsSeeder;
use Modules\Classroom\Database\Seeders\DemoClassroomSeeder;
use Modules\Communication\Database\Seeders\DemoCommunicationSeeder;
use Modules\Curriculum\Database\Seeders\DemoCurriculumSeeder;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Learning\Database\Seeders\DemoLearningSeeder;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Database\Seeders\DemoDstSeeder;
use Modules\Scheduling\Database\Seeders\DemoLiveBoardSeeder;
use Modules\Scheduling\Database\Seeders\DemoShiftsSeeder;
use Modules\Scheduling\Database\Seeders\DemoTimeOffSeeder;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Local demo data: academy "demo" (demo.<platform domain>, or header X-Academy: demo)
 * with one user per main role, an evening of sessions around now for the
 * live board, teacher shifts and proposals, plans and prices, and enrolled
 * students with invoices in every state (payable online on the demo gateway),
 * curricula, lesson reports with homework, teacher time off with a substitute
 * and an academy holiday, and the notifications all of that sent. Every password is "password". `composer demo` rebuilds it.
 */
class DatabaseSeeder extends Seeder
{
    public function run(PlatformApi $platform, TenantDirectory $directory, IdentityApi $identity, TenantContext $tenants): void
    {
        if ($directory->bySlug('demo') !== null) {
            $this->command?->info('Academy "demo" already exists; nothing to seed.');

            return;
        }

        $academy = $platform->createAcademy(new NewAcademy(
            slug: 'demo',
            nameAr: 'أكاديمية النور التجريبية',
            nameEn: 'Noor Demo Academy',
            baseCurrency: 'SAR',
            defaultTimezone: 'Asia/Riyadh',
            defaultLocale: 'ar',
            country: 'SA',
        ));

        $tenants->run($academy, function () use ($identity): void {
            $people = [
                ['هالة منصور', 'owner@demo.test', [Role::Owner, Role::Admin]],
                ['منسّق الجداول', 'scheduler@demo.test', [Role::Scheduler]],
                ['محاسب الأكاديمية', 'accountant@demo.test', [Role::Accountant]],
                ['أ. أحمد', 'teacher@demo.test', [Role::Teacher]],
                ['ولي أمر', 'guardian@demo.test', [Role::Guardian]],
                ['طالب', 'student@demo.test', [Role::Student]],
            ];

            foreach ($people as [$name, $email, $roles]) {
                $identity->createUser(new NewUser(name: $name, roles: $roles, email: $email, password: 'password', locale: 'ar', timezone: 'Africa/Cairo'));
            }

            $this->call([DemoCommunicationSeeder::class, DemoLiveBoardSeeder::class, DemoBillingSeeder::class, DemoShiftsSeeder::class, DemoCurriculumSeeder::class, DemoLearningSeeder::class, DemoClassroomSeeder::class, DemoPaymentsSeeder::class, DemoTimeOffSeeder::class, DemoDstSeeder::class]);
        });

        $this->command?->info('Seeded academy "demo". Sign in as owner@demo.test / password.');
    }
}
