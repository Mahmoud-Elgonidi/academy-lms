<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\SharedKernel\Tenancy\TenantContext;

/** Onboards an academy: the platform record plus its first owner account. */
final class CreateAcademyCommand extends Command
{
    protected $signature = 'academy:create
        {slug : Subdomain, e.g. "noor" for noor.<platform domain>}
        {--name-ar= : Arabic name}
        {--name-en= : English name}
        {--currency=SAR : ISO 4217 base currency}
        {--timezone=Asia/Riyadh : IANA timezone}
        {--locale=ar : Default language (ar|en)}
        {--country= : ISO 3166 country code}
        {--domain= : Custom domain (stays unverified for now)}
        {--owner-name= : Owner full name}
        {--owner-email= : Owner email}
        {--owner-password= : Owner password (asked for when omitted)}';

    protected $description = 'Create an academy and its owner account';

    public function handle(PlatformApi $platform, IdentityApi $identity, TenantContext $tenants): int
    {
        $slug = (string) $this->argument('slug');
        $ownerEmail = $this->option('owner-email') ?: $this->ask('Owner email');
        $ownerPassword = $this->option('owner-password') ?: $this->secret('Owner password');

        try {
            $academy = $platform->createAcademy(new NewAcademy(
                slug: $slug,
                nameAr: $this->option('name-ar') ?: $slug,
                nameEn: $this->option('name-en') ?: $slug,
                baseCurrency: strtoupper((string) $this->option('currency')),
                defaultTimezone: (string) $this->option('timezone'),
                defaultLocale: (string) $this->option('locale'),
                country: $this->option('country') ? strtoupper((string) $this->option('country')) : null,
                customDomain: $this->option('domain') ?: null,
            ));

            $tenants->run($academy, fn () => $identity->createUser(new NewUser(
                name: $this->option('owner-name') ?: (string) $ownerEmail,
                roles: [Role::Owner, Role::Admin],
                email: (string) $ownerEmail,
                password: (string) $ownerPassword,
            )));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->error("{$field}: ".implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $this->info("Academy {$academy->slug} created ({$academy->id}). Owner: {$ownerEmail}");

        return self::SUCCESS;
    }
}
