<?php

namespace Modules\Curriculum\Actions\Resources;

use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Models\NodeResource;

/** The words, the audience and a link's address of a lesson's resource, checked the same way when added or edited. */
final class ResourceRules
{
    public const TITLE_MAX = 160;

    /**
     * @param  array{title_ar?: ?string, title_en?: ?string, audience?: ?string, title_fallback?: ?string}  $data
     * @return array{title_ar: string, title_en: string, audience: string}
     */
    public static function details(array $data): array
    {
        $ar = trim((string) ($data['title_ar'] ?? ''));
        $en = trim((string) ($data['title_en'] ?? ''));
        if ($ar === '' && $en === '') {
            $ar = $en = trim((string) ($data['title_fallback'] ?? ''));
        }
        if (($ar === '' && $en === '') || mb_strlen($ar) > self::TITLE_MAX || mb_strlen($en) > self::TITLE_MAX) {
            throw ValidationException::withMessages(['title_ar' => __('curriculum::curriculum.resources.errors.title', ['max' => self::TITLE_MAX])]);
        }
        $audience = $data['audience'] ?? 'all';
        if (! in_array($audience, NodeResource::AUDIENCES, true)) {
            throw ValidationException::withMessages(['audience' => __('curriculum::curriculum.resources.errors.audience')]);
        }

        return ['title_ar' => $ar !== '' ? $ar : $en, 'title_en' => $en !== '' ? $en : $ar, 'audience' => $audience];
    }

    /** A web address people can open: http or https, with a host, up to 2000 characters. */
    public static function url(?string $url): string
    {
        $url = trim((string) $url);
        $parts = parse_url($url);
        if ($url === '' || mb_strlen($url) > 2000 || $parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages(['url' => __('curriculum::curriculum.resources.errors.url')]);
        }

        return $url;
    }
}
