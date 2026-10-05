<?php

namespace Modules\Platform\Contracts;

use Modules\Platform\Contracts\DTOs\Branding;

/** The current academy's name, logo, colours and fonts. */
interface BrandingApi
{
    public function current(): Branding;
}
