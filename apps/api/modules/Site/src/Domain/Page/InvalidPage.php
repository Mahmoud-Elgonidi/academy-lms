<?php

namespace Modules\Site\Domain\Page;

use DomainException;

/** A page's document that cannot be kept: the problems, each under its field's path (Problems). */
final class InvalidPage extends DomainException
{
    /** @param  array<string, array{0: string, 1: array<string, scalar>}>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('The page has '.count($problems).' problem(s): '.implode(', ', array_keys($problems)));
    }
}
