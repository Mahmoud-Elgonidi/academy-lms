<?php

namespace Modules\SharedKernel\Http;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A problem of its own type, with extra members the client acts on
 * (RFC 7807 extensions), e.g. "two-factor-required" with the token for the
 * second step. Its title comes from shared::problems.{type}.
 */
final class ProblemException extends HttpException
{
    /** @param  array<string, mixed>  $extra */
    public function __construct(int $status, public readonly string $type, string $detail = '', public readonly array $extra = [])
    {
        parent::__construct($status, $detail);
    }
}
