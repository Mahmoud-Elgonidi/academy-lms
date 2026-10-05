<?php

namespace Modules\Catalog;

use Modules\Catalog\Actions\CreateSubject;
use Modules\Catalog\Contracts\CatalogApi;

final class CatalogService implements CatalogApi
{
    public function __construct(private readonly CreateSubject $createSubject) {}

    public function createSubject(string $nameAr, string $nameEn): string
    {
        return $this->createSubject->handle($nameAr, $nameEn)->id;
    }
}
