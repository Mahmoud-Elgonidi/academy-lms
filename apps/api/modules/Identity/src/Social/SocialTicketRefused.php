<?php

namespace Modules\Identity\Social;

use RuntimeException;

/** A hand-off ticket that is unknown, used, too old, for another academy, or not from this browser: start again. */
final class SocialTicketRefused extends RuntimeException {}
