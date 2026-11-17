<?php

namespace Modules\Site\Support;

use Illuminate\Validation\ValidationException;
use Modules\Site\Domain\Page\InvalidPage;

/**
 * The builder's words for what the domain finds: a document's problems as
 * validation messages under their fields, and publish rules as sentences
 * that name the block and the field ("«الواجهة» · العنوان: …").
 */
final class PageWords
{
    /**
     * The problems as a ValidationException, each under its path with a prefix
     * (the builder's form puts its state under "data.").
     */
    public static function invalid(InvalidPage $e, string $prefix = ''): ValidationException
    {
        $messages = [];
        foreach ($e->problems as $path => [$code, $params]) {
            $messages[$prefix.$path] = self::problem($path, $code, $params);
        }

        return ValidationException::withMessages($messages);
    }

    /** @param  array<string, scalar>  $params */
    public static function problem(string $path, string $code, array $params = []): string
    {
        return (string) __('site::builder.errors.'.$code, $params + ['field' => self::fieldOfPath($path)]);
    }

    /**
     * One publish rule's item as a sentence.
     *
     * @param  array{code: string, block: ?string, type: ?string, field: ?string, params: array<string, scalar>}  $item
     */
    public static function rule(array $item): string
    {
        $params = $item['params'];
        if (isset($params['missing'])) {
            $params['missing'] = (string) __('site::builder.languages.'.$params['missing']);
        }
        $text = (string) __('site::builder.rules.'.$item['code'], $params);
        $where = [];
        if ($item['type'] !== null) {
            $where[] = '«'.self::type($item['type']).'»';
        }
        if ($item['field'] !== null) {
            $where[] = self::field($item['field']);
        }

        return $where === [] ? $text : implode(' · ', $where).': '.$text;
    }

    public static function type(string $type): string
    {
        return (string) __('site::builder.types.'.$type.'.name');
    }

    /** "items.2.title" → "العنصر 3 · العنوان". */
    public static function field(string $path): string
    {
        $parts = [];
        foreach (explode('.', $path) as $part) {
            if ($part === '') {
                continue;
            }
            if (ctype_digit($part)) {
                $parts[] = (string) __('site::builder.fields.item_n', ['n' => (int) $part + 1]);

                continue;
            }
            if ($part === 'ar' || $part === 'en') {
                $parts[] = (string) __('site::builder.languages.'.$part);

                continue;
            }
            $key = 'site::builder.fields.'.$part;
            $parts[] = trans()->has($key) ? (string) __($key) : $part;
        }

        return implode(' · ', $parts);
    }

    /** A document path ("blocks.3.data.items.0.title.ar") → the field's words. */
    private static function fieldOfPath(string $path): string
    {
        $path = (string) preg_replace('/^blocks\.\d+\.(data\.)?/', '', $path);
        $path = (string) preg_replace('/^settings\./', '', $path);

        return self::field($path);
    }
}
