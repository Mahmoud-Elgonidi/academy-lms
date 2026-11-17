<?php

namespace Modules\Site\Domain\Page;

/**
 * What PublishRules needs to know beyond the document: the academy's media
 * files (kind, type and size) by id. Pure data.
 */
final readonly class Facts
{
    /**
     * @param  array<string, array{kind: string, mime: string, width: ?int, height: ?int}>  $media
     */
    public function __construct(public array $media = []) {}

    /** @return array{kind: string, mime: string, width: ?int, height: ?int}|null */
    public function media(?string $id): ?array
    {
        return $id !== null ? ($this->media[$id] ?? null) : null;
    }
}
