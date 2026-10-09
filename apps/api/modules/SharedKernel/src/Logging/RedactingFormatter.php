<?php

namespace Modules\SharedKernel\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/** Wraps a channel's formatter: the line it writes goes through the Redactor last, exceptions and their SQL included. */
final class RedactingFormatter implements FormatterInterface
{
    public function __construct(private readonly FormatterInterface $inner) {}

    public function format(LogRecord $record): mixed
    {
        return $this->clean($this->inner->format($record));
    }

    public function formatBatch(array $records): mixed
    {
        return $this->clean($this->inner->formatBatch($records));
    }

    private function clean(mixed $formatted): mixed
    {
        return match (true) {
            is_string($formatted) => Redactor::text($formatted),
            is_array($formatted) => array_map(fn ($part) => $this->clean($part), $formatted),
            default => $formatted,
        };
    }
}
