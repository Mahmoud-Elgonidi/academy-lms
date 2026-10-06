<?php

namespace Modules\Billing\Payments;

use RuntimeException;

/** The gateway refused or could not be reached; the message is for the logs and the back office, not the payer. */
final class GatewayException extends RuntimeException {}
