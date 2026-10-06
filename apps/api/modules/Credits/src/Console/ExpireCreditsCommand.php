<?php

namespace Modules\Credits\Console;

use Illuminate\Console\Command;
use Modules\Credits\Actions\ExpireCredits;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Hourly: minutes past their date lapse, in each academy, as that academy. */
final class ExpireCreditsCommand extends Command
{
    protected $signature = 'credits:expire';

    protected $description = 'Expire credit lots past their date in every academy';

    public function handle(EachTenant $each, ExpireCredits $expire): int
    {
        $lapsed = $each->sum(fn () => $expire->handle());
        $this->info("{$lapsed} lot(s) expired.");

        return self::SUCCESS;
    }
}
