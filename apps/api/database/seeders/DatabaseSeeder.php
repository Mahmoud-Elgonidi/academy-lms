<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Database\Seeders\DemoLiveBoardSeeder;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Local demo data: academy "demo" (demo.<platform domain>, or header X-Academy: demo)
 * with one user per main role and an evening of sessions around now for the
 * live board. Every password is "password". `composer demo` rebuilds it.
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
                ['أ. أحمد', 'teacher@demo.test', [Role::Teacher]],
                ['ولي أمر', 'guardian@demo.test', [Role::Guardian]],
                ['طالب', 'student@demo.test', [Role::Student]],
            ];

            foreach ($people as [$name, $email, $roles]) {
                $identity->createUser(new NewUser(name: $name, roles: $roles, email: $email, password: 'password', locale: 'ar', timezone: 'Africa/Cairo'));
            }

            $this->call(DemoLiveBoardSeeder::class);
        });

        $this->command?->info('Seeded academy "demo". Sign in as owner@demo.test / password.');
    }
}
