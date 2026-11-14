<?php

namespace Modules\Platform\Payments;

use RuntimeException;

/** Stripe did not do what was asked: its code only (never a key, a reference or Stripe's words). */
final class StripeException extends RuntimeException {}
