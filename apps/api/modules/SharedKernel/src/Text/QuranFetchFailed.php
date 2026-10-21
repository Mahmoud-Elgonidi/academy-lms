<?php

namespace Modules\SharedKernel\Text;

use RuntimeException;

/** The Quran's files were not fetched or did not pass their checks; the message says why, in the reader's words. */
final class QuranFetchFailed extends RuntimeException {}
