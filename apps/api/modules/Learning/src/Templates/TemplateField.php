<?php

namespace Modules\Learning\Templates;

/**
 * One question of a report template: what it asks (in Arabic and English),
 * the kind of answer (stars 1–5, a number with a unit, one of a few choices,
 * yes or no, a short text), whether it must be answered, and whether the
 * family reads the answer or it stays for the academy.
 */
final readonly class TemplateField
{
    public const TYPES = ['rating', 'number', 'choice', 'yes_no', 'text'];

    /** @param  list<array{key: string, ar: string, en: string}>  $options */
    public function __construct(
        public string $key,
        public string $labelAr,
        public string $labelEn,
        public string $type,
        public bool $required = false,
        public bool $family = true,
        public array $options = [],
        public ?int $min = null,
        public ?int $max = null,
        public ?string $unitAr = null,
        public ?string $unitEn = null,
        public ?string $unitArFew = null,   // the Arabic unit after 3 to 10 (آيات), when it differs
    ) {}

    /** @param  array<string, mixed>  $f */
    public static function fromArray(array $f): self
    {
        return new self(
            key: (string) $f['key'],
            labelAr: (string) $f['label_ar'],
            labelEn: (string) $f['label_en'],
            type: (string) $f['type'],
            required: (bool) ($f['required'] ?? false),
            family: (bool) ($f['family'] ?? true),
            options: array_values(array_map(fn (array $o) => ['key' => (string) $o['key'], 'ar' => (string) $o['ar'], 'en' => (string) $o['en']], $f['options'] ?? [])),
            min: isset($f['min']) ? (int) $f['min'] : null,
            max: isset($f['max']) ? (int) $f['max'] : null,
            unitAr: isset($f['unit_ar']) && $f['unit_ar'] !== '' ? (string) $f['unit_ar'] : null,
            unitEn: isset($f['unit_en']) && $f['unit_en'] !== '' ? (string) $f['unit_en'] : null,
            unitArFew: isset($f['unit_ar_few']) && $f['unit_ar_few'] !== '' ? (string) $f['unit_ar_few'] : null,
        );
    }

    /** @return array<string, mixed> as stored */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label_ar' => $this->labelAr,
            'label_en' => $this->labelEn,
            'type' => $this->type,
            'required' => $this->required,
            'family' => $this->family,
            'options' => $this->type === 'choice' ? $this->options : null,
            'min' => $this->type === 'number' ? $this->min : null,
            'max' => $this->type === 'number' ? $this->max : null,
            'unit_ar' => $this->type === 'number' ? $this->unitAr : null,
            'unit_en' => $this->type === 'number' ? $this->unitEn : null,
            'unit_ar_few' => $this->type === 'number' ? $this->unitArFew : null,
        ], fn ($v) => $v !== null);
    }

    public function label(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->labelAr : $this->labelEn;
    }

    /** The unit, after this many when given: in Arabic, 3 to 10 take the plural (6 آيات, 12 آية). */
    public function unit(?string $locale = null, int|float|null $count = null): ?string
    {
        if (($locale ?? app()->getLocale()) !== 'ar') {
            return $this->unitEn;
        }
        $few = $count !== null && $count == (int) $count && $count >= 3 && $count <= 10;

        return $few && $this->unitArFew !== null ? $this->unitArFew : $this->unitAr;
    }

    /** @return array<string, string> choice key => its words in this language */
    public function choices(?string $locale = null): array
    {
        $ar = ($locale ?? app()->getLocale()) === 'ar';

        return collect($this->options)->mapWithKeys(fn (array $o) => [$o['key'] => $ar ? $o['ar'] : $o['en']])->all();
    }
}
