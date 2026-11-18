<?php

namespace Modules\Site\Domain\Page;

/**
 * What is wrong with a page's document, each under the path of the field it
 * is about ("blocks.3.data.title.ar") with a code and its values; the
 * Actions word them (site::builder.errors.{code}). Pure.
 */
final class Problems
{
    /** @var array<string, array{0: string, 1: array<string, scalar>}> */
    private array $all = [];

    /** @param  array<string, scalar>  $params */
    public function add(string $path, string $code, array $params = []): void
    {
        $this->all[$path] ??= [$code, $params];
    }

    public function none(): bool
    {
        return $this->all === [];
    }

    /** @return array<string, array{0: string, 1: array<string, scalar>}> */
    public function all(): array
    {
        return $this->all;
    }

    /** @throws InvalidPage */
    public function throwIfAny(): void
    {
        if ($this->all !== []) {
            throw new InvalidPage($this->all);
        }
    }
}
