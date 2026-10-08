<?php

namespace Modules\Learning\Contracts\DTOs;

/** One of a lesson's materials as a page shows it: what it is, its name, and an address to open it. */
final readonly class MaterialLine
{
    public function __construct(
        public string $id,
        public string $type,            // pdf | audio | video | image | link
        public string $title,
        public string $url,             // a link's address, or a signed one for a file
        public bool $teachersOnly,
        public ?int $size = null,       // bytes, for a file
    ) {}

    /** @return array{id: string, type: string, title: string, url: string, teachers_only: bool, size: ?int} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'title' => $this->title, 'url' => $this->url, 'teachers_only' => $this->teachersOnly, 'size' => $this->size];
    }
}
