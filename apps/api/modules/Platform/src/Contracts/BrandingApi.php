<?php

namespace Modules\Platform\Contracts;

use Modules\Platform\Contracts\DTOs\Branding;

/** The current academy's name, logo, colours and fonts. */
interface BrandingApi
{
    public function current(): Branding;

    /**
     * The font families an academy may pick (its branding, and its page's builder).
     *
     * @return list<string>
     */
    public function fontFamilies(): array;

    /** One stylesheet that loads these families (unknown names are left out). */
    public function fontsUrl(string ...$families): string;
}
