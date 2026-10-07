<?php

namespace Modules\Payroll\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Payroll\Filament\Pages\Payroll;
use Modules\Payroll\Filament\Pages\PayrollStatement;
use Modules\Payroll\Filament\Resources\RateCards\RateCardResource;

/** Payroll in the academy back office: the month's pay, each teacher's statement, and the rate cards. */
final class PayrollPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'payroll';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Payroll::class, PayrollStatement::class])->resources([RateCardResource::class]);
    }

    public function boot(Panel $panel): void {}
}
