<?php

namespace Modules\Communication\Templates;

use InvalidArgumentException;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;

/** The templates modules declared at boot, by key. */
final class TemplateRegistry
{
    /** @var array<string, TemplateDefinition> */
    private array $templates = [];

    public function define(TemplateDefinition $template): void
    {
        $this->templates[$template->key] = $template;
    }

    public function get(string $key): TemplateDefinition
    {
        return $this->templates[$key] ?? throw new InvalidArgumentException("No notification template [{$key}].");
    }

    /** @return array<string, TemplateDefinition> */
    public function all(): array
    {
        return $this->templates;
    }
}
