<?php

/*
 * A second process for the metering race (UsageLedgerTest): boots the app as
 * the tests do and tries to hold credits for one academy, printing "held" or
 * "refused". Arguments: academy id, amount, the test's time.
 */

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Metering\NotEnoughBalance;
use Modules\SharedKernel\Plans\Meter;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// The test's own clock, so both processes are in the same month.
CarbonImmutable::setTestNow(CarbonImmutable::parse((string) $argv[3]));

try {
    $app->make(Meters::class)->reserve((string) $argv[1], Meter::AiCredits, (int) $argv[2], ['subject' => 'practice_items']);
    echo 'held';
} catch (NotEnoughBalance) {
    echo 'refused';
}
