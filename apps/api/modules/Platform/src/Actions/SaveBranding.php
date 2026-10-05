<?php

namespace Modules\Platform\Actions;

use Modules\Platform\Branding\BrandingService;
use Modules\Platform\Branding\BrandTokens;
use Modules\Platform\Branding\TemplateCatalog;
use Modules\Platform\Models\AcademyBranding;

/**
 * Applies a look to the current academy: a template as is, or edited tokens.
 * $templateRef is the template the tokens started from; it is kept even when
 * the tokens were edited, so the screen can say "Noor, edited".
 */
final class SaveBranding
{
    public function __construct(
        private readonly TemplateCatalog $catalog,
        private readonly BrandingService $branding,
    ) {}

    public function handle(BrandTokens $tokens, ?string $templateRef, ?string $actorId = null): AcademyBranding
    {
        $ref = $this->catalog->find($templateRef) !== null ? $templateRef : null;

        $row = AcademyBranding::query()->firstOrNew();
        $row->fill([
            'template_ref' => $ref,
            'tokens' => $tokens->toArray(),
            'updated_by' => $actorId,
        ])->save();

        $this->branding->forget();

        return $row;
    }
}
