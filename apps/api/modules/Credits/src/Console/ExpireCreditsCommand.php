<?php

namespace Modules\Credits\Console;

use Illuminate\Console\Command;
use Modules\Credits\Actions\ExpireCredits;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Hourly: minutes past their date lapse, and paid-for minutes lapsing within a week are told once, in each academy, as that academy. */
final class ExpireCreditsCommand extends Command
{
    protected $signature = 'credits:expire';

    protected $description = 'Expire credit lots past their date, and tell of those lapsing soon, in every academy';

    public function handle(EachTenant $each, ExpireCredits $expire): int
    {
        $lapsed = $each->sum(fn () => $expire->handle());
        $told = $each->sum(fn () => $expire->notice());
        $this->info("{$lapsed} lot(s) expired, {$told} told they will.");

        return self::SUCCESS;
    }
}
