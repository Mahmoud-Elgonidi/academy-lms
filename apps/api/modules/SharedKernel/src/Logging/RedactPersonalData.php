<?php

namespace Modules\SharedKernel\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\FormattableHandlerInterface;

/**
 * A log channel's tap (config/logging.php): every handler that writes
 * lines writes them through the RedactingFormatter.
 */
final class RedactPersonalData
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface && ! $handler->getFormatter() instanceof RedactingFormatter) {
                $handler->setFormatter(new RedactingFormatter($handler->getFormatter()));
            }
        }
    }
}
