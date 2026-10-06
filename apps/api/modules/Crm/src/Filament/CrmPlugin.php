<?php

namespace Modules\Crm\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Crm\Filament\Pages\LeadPage;
use Modules\Crm\Filament\Pages\Leads;

/** The CRM in the academy back office: the leads board and each lead's page. */
final class CrmPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'crm';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Leads::class, LeadPage::class]);
    }

    public function boot(Panel $panel): void {}
}
