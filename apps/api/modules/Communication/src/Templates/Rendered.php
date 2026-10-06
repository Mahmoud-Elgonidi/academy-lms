<?php

namespace Modules\Communication\Templates;

final readonly class Rendered
{
    public function __construct(public string $title, public string $body) {}
}
