<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Actions\FillLearnerCountries;
use Modules\SharedKernel\Tenancy\EachTenant;

/**
 * Once, in each academy: the learners and parents the CRM made before their
 * lead's country went on them take it now, where they have none (their
 * migration runs it on every server; safe to run again by hand).
 */
final class FillLearnerCountriesCommand extends Command
{
    protected $signature = 'crm:learner-countries';

    protected $description = "Give the CRM's learners and parents their lead's country where they have none, in every academy";

    public function handle(EachTenant $each, FillLearnerCountries $fill): int
    {
        $filled = $each->sum(fn () => $fill->handle());
        $this->info("{$filled} profile(s) took their lead's country.");

        return self::SUCCESS;
    }
}
