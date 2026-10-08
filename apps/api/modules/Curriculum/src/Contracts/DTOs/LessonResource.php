<?php

namespace Modules\Curriculum\Contracts\DTOs;

/**
 * A lesson's file or link, as other modules see it. A file has no address
 * here: whoever shows it to someone allowed to see it signs one.
 */
final readonly class LessonResource
{
    public function __construct(
        public string $id,
        public string $nodeId,
        public string $kind,            // file | link
        public string $type,            // pdf | audio | video | image | link
        public string $titleAr,
        public string $titleEn,
        public string $audience,        // all | teachers
        public ?string $url = null,     // a link's address
        public ?string $fileName = null,
        public ?string $mime = null,
        public ?int $size = null,       // bytes
    ) {}

    public function title(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->titleAr : $this->titleEn;
    }

    public function forTeachersOnly(): bool
    {
        return $this->audience === 'teachers';
    }
}
