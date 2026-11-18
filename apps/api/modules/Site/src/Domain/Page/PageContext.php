<?php

namespace Modules\Site\Domain\Page;

use Closure;

/**
 * What Localize needs beside the document: the language, the academy's
 * files (with their address already made), its subjects in order, and its
 * branding fonts with the way to load others. Pure data.
 */
final readonly class PageContext
{
    /**
     * @param  array<string, array{kind: string, mime: string, url: string, width: ?int, height: ?int, alt: array{ar: string, en: string}}>  $media
     * @param  list<string>  $subjects  every subject id the academy teaches, in its order
     * @param  array{heading: string, body: string}  $brandFonts
     * @param  Closure(string ...): string  $fontsUrl  the stylesheet that loads these families
     */
    public function __construct(
        public string $locale,
        public array $media,
        public array $subjects,
        public array $brandFonts,
        public Closure $fontsUrl,
    ) {}
}
