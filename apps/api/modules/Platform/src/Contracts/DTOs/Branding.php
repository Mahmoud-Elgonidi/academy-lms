<?php

namespace Modules\Platform\Contracts\DTOs;

/** The current academy's name, logo and look, as every client renders it. */
final readonly class Branding
{
    /**
     * @param  array{primary: string, attention: string, danger: string, info: string, neutral: string, heading_font: string, body_font: string, radius: string}  $tokens
     * @param  array{sm: string, md: string, lg: string, xl: string}  $radii
     */
    public function __construct(
        public string $nameAr,
        public string $nameEn,
        public array $tokens,
        public array $radii,
        public string $fontsUrl,
        public ?string $logoUrl,
        public ?string $faviconUrl,
        public ?string $templateRef,
        public ?string $templateName,
        public bool $modified,
    ) {}

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->nameAr : $this->nameEn;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => ['ar' => $this->nameAr, 'en' => $this->nameEn],
            'tokens' => $this->tokens,
            'radii' => $this->radii,
            'fonts_url' => $this->fontsUrl,
            'logo_url' => $this->logoUrl,
            'favicon_url' => $this->faviconUrl,
            'template' => ['ref' => $this->templateRef, 'name' => $this->templateName, 'modified' => $this->modified],
        ];
    }
}
