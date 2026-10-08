<?php

namespace Modules\Identity\Social;

use RuntimeException;

/** An ID token that is not one Google or Apple gave our clients just now. The reason is for logs and tests, never for the caller. */
final class InvalidIdToken extends RuntimeException {}
