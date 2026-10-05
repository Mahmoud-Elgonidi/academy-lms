<?php

namespace Modules\Platform\Branding;

use Illuminate\Support\Collection;
use Modules\Platform\Models\BrandingTemplate;

/**
 * Every template the current academy can pick: the built-in ones and those it
 * saved. A template reference is "builtin:{key}" or "saved:{uuid}".
 */
final class TemplateCatalog
{
    /** @return list<array{ref: string, name: string, tokens: BrandTokens, builtIn: bool}> */
    public function all(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        $builtIn = collect(BuiltInTemplates::all())->map(fn (array $t, string $key) => [
            'ref' => 'builtin:'.$key,
            'name' => $locale === 'ar' ? $t['name_ar'] : $t['name_en'],
            'tokens' => $t['tokens'],
            'builtIn' => true,
        ]);

        $saved = $this->saved()->map(fn (BrandingTemplate $t) => [
            'ref' => $t->ref(),
            'name' => $t->name,
            'tokens' => $t->brandTokens(),
            'builtIn' => false,
        ]);

        return $builtIn->values()->concat($saved)->all();
    }

    /** @return Collection<int, BrandingTemplate> */
    public function saved(): Collection
    {
        return BrandingTemplate::query()->orderBy('created_at')->get();
    }

    public function find(?string $ref): ?array
    {
        if ($ref === null) {
            return null;
        }

        return collect($this->all())->firstWhere('ref', $ref);
    }

    public function savedTemplate(?string $ref): ?BrandingTemplate
    {
        if ($ref === null || ! str_starts_with($ref, 'saved:')) {
            return null;
        }

        $id = substr($ref, 6);

        return preg_match('/^[0-9a-f-]{36}$/i', $id) ? BrandingTemplate::query()->find($id) : null;
    }
}
